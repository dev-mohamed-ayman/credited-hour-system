<?php

namespace App\Services;

use App\Enums\ExamSessionStatus;
use App\Exceptions\ExamScheduleException;
use App\Models\ExamCommittee;
use App\Models\ExamSeatAssignment;
use App\Models\ExamSession;
use Illuminate\Support\Facades\DB;

/**
 * Owns committee distribution & seating rules (FR-011..FR-018).
 * The single write seam for exam_seat_assignments — any future
 * legacy importer must reuse this service (research R1).
 */
class ExamSeatingService
{
    /**
     * Incremental, placement-preserving generation (research R7):
     * remove ineligible, keep existing (incl. manual moves), place newcomers
     * in stable name order into committees with remaining capacity.
     */
    public function generateDistribution(ExamSession $session): void
    {
        DB::transaction(function () use ($session) {
            $committees = $session->committees()->orderBy('id')->get();

            if ($committees->isEmpty()) {
                throw new ExamScheduleException('يجب إضافة لجنة واحدة على الأقل قبل توليد التوزيع');
            }

            $examineeIds = app(ExamScheduleService::class)->examineeIds($session);

            $session->seatAssignments()
                ->whereNotIn('student_id', $examineeIds ?: [0])
                ->delete();

            $placedIds = $session->seatAssignments()->pluck('student_id')->all();
            $unplaced = array_values(array_diff($examineeIds, $placedIds));

            $load = $session->committees()
                ->withCount('assignments')
                ->orderBy('id')
                ->get()
                ->mapWithKeys(fn (ExamCommittee $committee) => [
                    $committee->id => (int) $committee->capacity - (int) $committee->assignments_count,
                ]);

            $totalCapacity = (int) $session->committees()->sum('capacity');

            if (count($unplaced) > $load->sum()) {
                throw new ExamScheduleException(
                    'عدد الممتحنين ('.count($examineeIds).') يتجاوز إجمالي سعة اللجان ('.$totalCapacity.')'
                );
            }

            foreach ($unplaced as $studentId) {
                $committeeId = $load->search(fn (int $remaining) => $remaining > 0);

                if ($committeeId === false) {
                    throw new ExamScheduleException(
                        'عدد الممتحنين ('.count($examineeIds).') يتجاوز إجمالي سعة اللجان ('.$totalCapacity.')'
                    );
                }

                $committee = ExamCommittee::findOrFail($committeeId);

                ExamSeatAssignment::create([
                    'exam_session_id' => $session->id,
                    'exam_committee_id' => $committee->id,
                    'student_id' => $studentId,
                    'seat_number' => $this->nextSeatNumber($committee),
                ]);

                $load[$committeeId] = $load[$committeeId] - 1;
            }

            $this->revertToDraft($session);
        });
    }

    public function moveStudent(ExamSeatAssignment $assignment, ExamCommittee $target): void
    {
        if ($assignment->exam_committee_id === $target->id) {
            return;
        }

        DB::transaction(function () use ($assignment, $target) {
            $occupied = $target->assignments()->whereKeyNot($assignment->id)->count();

            if ($occupied >= $target->capacity) {
                throw new ExamScheduleException(
                    "اللجنة \"{$target->name}\" ممتلئة ({$occupied}/{$target->capacity})"
                );
            }

            $assignment->update([
                'exam_committee_id' => $target->id,
                'seat_number' => $this->nextSeatNumber($target),
            ]);

            $this->revertToDraft($assignment->examSession);
        });
    }

    /**
     * Derived staleness (research R9): audience ≠ assignments, or any
     * committee over its capacity (FR-018). Never persisted.
     */
    public function isSeatingStale(ExamSession $session): bool
    {
        $examineeIds = app(ExamScheduleService::class)->examineeIds($session);
        $assignedIds = $session->seatAssignments()->pluck('student_id')->all();

        if (array_diff($examineeIds, $assignedIds) !== [] || array_diff($assignedIds, $examineeIds) !== []) {
            return true;
        }

        return $session->committees()
            ->withCount('assignments')
            ->get()
            ->contains(fn (ExamCommittee $committee) => (int) $committee->assignments_count > (int) $committee->capacity);
    }

    public function totalCapacity(ExamSession $session): int
    {
        return (int) $session->committees()->sum('capacity');
    }

    /**
     * Seat-number format seam (research R8): sequential within the committee,
     * gaps allowed, existing numbers never renumbered. Swap the body here to
     * reintroduce the legacy 5-digit level-prefixed scheme later.
     */
    /**
     * Any distribution edit on a published session reverts it to draft (FR-020).
     */
    private function revertToDraft(ExamSession $session): void
    {
        if ($session->status === ExamSessionStatus::PUBLISHED) {
            $session->update(['status' => ExamSessionStatus::DRAFT]);
        }
    }

    private function nextSeatNumber(ExamCommittee $committee): string
    {
        $max = $committee->assignments()
            ->select(DB::raw('MAX(CAST(seat_number AS UNSIGNED)) as aggregate'))
            ->value('aggregate');

        return (string) (((int) $max) + 1);
    }
}
