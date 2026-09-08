# Feature Specification: Exam Scheduling, Committees & Seating

**Feature Branch**: `002-exam-scheduling`

**Created**: 2026-09-08

**Status**: Draft

**Input**: User description: "Read Exam Scheduling — Feature Spec (What & Why).md" (Arabic proposal document defining exam scheduling, committees, and seating numbers for the Credit Hour System)

## Clarifications

### Session 2026-09-08

- Q: Should each semester carry a configurable exam window (from/to dates) that sessions are validated against, or may sessions be scheduled on any date? → A: Yes — a configurable exam window per semester; sessions outside the window are rejected.
- Q: Which exam types are in the first phase — Regular only, or Regular + Second-sitting (resit) + Improvement? → A: Regular + Second-sitting + Improvement from the start, with activation per need.
- Q: Is legacy-format file import of committees/seating required in the first phase alongside automatic generation? → A: No — automatic generation alone for launch; file import is deferred to a later phase.
- Q: Should publishing apply to each exam session individually, or to the whole term's schedule at once? → A: Per-session publish/unpublish — each session becomes visible to students independently once it passes the publish checks.
- Q: When the admin regenerates a distribution after having manually moved students between committees, what must happen to those manual adjustments? → A: Regeneration keeps existing placements (including manual moves), removes ineligible students, and only places students not yet placed; idempotency means same audience + same adjustments ⇒ same result.
- Q: If two admins save overlapping exam sessions (same students or same venue) at nearly the same moment, what must the outcome be? → A: Absolute guarantee — rules are re-checked at save time so exactly one save succeeds; the schedule can never contain a conflict.
- Q: What should happen when an admin schedules a session for a semester whose exam window has not been configured yet? → A: Optional guard — with no window configured any date is accepted; once set, the window is enforced for new/edited sessions.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Conflict-free exam session scheduling (Priority: P1)

A student-affairs admin (exam committee) picks an academic year and semester (defaulting to the current one) and is shown exactly the courses that have approved registrations in that term — that list, not the full course catalog, is the exam map. For each course the admin records an exam session: date, start time, end time, exam type, and optional notes. Before anything is saved, the system enforces the hard rules: one session per course+year+semester+type combination, end time after start time, no student who is registered in two of these courses can hold two overlapping exams, and no venue can host two overlapping sessions on the same day. Every rejection names the offending parties in clear Arabic (affected students and both courses for a student clash; course, venue, and times for a venue clash).

**Why this priority**: This is the core promise of the feature. The legacy process accepted free-text dates and times from spreadsheet files with zero conflict checking — the single most damaging gap this feature closes. Without session scheduling and conflict prevention, committees, seating, and publishing have nothing to attach to.

**Independent Test**: Pre-provision two courses with overlapping approved registrations and two venues (at the data level); then: (a) create a valid session and confirm it is saved; (b) create an overlapping session for a shared student → rejected with the student named; (c) create an overlapping session in the same venue → rejected with the conflicting session named; (d) attempt a second session for the same course+year+semester+type → rejected. All outcomes verifiable without the other stories' screens.

**Acceptance Scenarios**:

1. **Given** course "Statistics" has approved registrations in semester 1 of 2025/2026, **When** the admin records its session as 2026-01-15, 9:00–11:00, Regular type, **Then** the session is saved with state "draft" and appears in the term's exam board.
2. **Given** student "Ahmed" is approved in two courses E1 and E2, **When** the admin schedules E1 and E2 on the same date 9:00–11:00, **Then** the second save is rejected with a report naming Ahmed and both courses.
3. **Given** "Hall A" hosts a session 9:00–11:00 on 2026-01-15, **When** the admin schedules another session in "Hall A" 10:30–12:00 the same day, **Then** it is rejected with a message naming the conflicting course, venue, and time window.
4. **Given** two sessions on the same day 9:00–11:00 and 12:00–14:00 with no overlap, **When** the admin saves the second, **Then** it is accepted (same-day non-overlapping exams are allowed).
5. **Given** a course already has a Regular session in semester 1 of 2025/2026, **When** the admin creates another Regular session for the same course+term, **Then** it is rejected; creating a Second-sitting session for the same course+term is allowed (type distinguishes them).
6. **Given** a staff member without the exam-session create permission, **When** they open the add-session page or trigger the save action directly, **Then** access is denied (403), not merely hidden.

---

### User Story 2 - Committees, automatic distribution & seating numbers (Priority: P2)

For each session the admin defines the exam committees: which venue each sits in, its name, and its seat capacity. One click distributes the session's examinees across the committees — split in a stable alphabetical-by-name order like the current seat-number generator — and gives every student a seating number that is unique within their committee. The admin sees the full distribution (seating number / student code / name / section per committee), can move students between committees, and can regenerate the distribution at any time. Regeneration keeps existing placements (including manual moves), removes students who are no longer eligible, and only places students not yet placed — so the same audience against the same placement state always produces the same result and corrections are never lost. The system refuses any distribution whose examinee count exceeds total committee capacity, and flags a session "distribution out of date" whenever a new approved registration appears after distribution.

**Why this priority**: Committees and seating turn "when" into "where and which desk" — the second thing students actually need. It depends on sessions (US1) existing, but is independently valuable: a schedule with dates but no rooms is already better than the legacy mailbox of spreadsheets.

**Independent Test**: With one session and 120 approved registrations plus two committees of capacity 60, generate the distribution and confirm: all 120 placed, none over capacity, alphabetical stability, seating numbers unique per committee, re-generation yields identical results; then add a 121st approved registration and confirm the session is flagged "distribution out of date"; then attempt a distribution of 120 examinees into committees totaling 100 seats → rejected.

**Acceptance Scenarios**:

1. **Given** course "Statistics" in semester 1 of 2025/2026 has 120 approved registrations, **When** the admin sets 2026-01-15, 9:00–11:00, venue "Hall A" with two committees of capacity 60+60 and presses "generate distribution", **Then** the 120 students are split across the two committees in alphabetical order and each student receives a seating number unique within their committee.
2. **Given** a session has 120 examinees and committees whose total capacity is 100, **When** the admin saves or publishes the distribution, **Then** it is rejected with: "عدد الممتحنين (120) يتجاوز إجمالي سعة اللجان (100)".
3. **Given** a distribution has been generated, **When** a new approved registration for that course appears, **Then** the session is marked "distribution out of date" and cannot be published again until the distribution is regenerated (new students are absorbed on regeneration).
4. **Given** a distribution exists, **When** a registration is rejected/cancelled and the distribution is regenerated, **Then** that student is no longer placed and holds no seating number for the session.
5. **Given** a committee's capacity is reduced below the number currently placed in it, **When** the admin reviews the session, **Then** regeneration is required and no student is silently dropped.

---

### User Story 3 - Publish gate & personal student exam table (Priority: P3)

Nothing exam-related is visible to students until the admin publishes — publishing is per session: each session becomes visible independently once it passes the publish checks, so a late-added resit can go live without touching already-published sessions. The publish action for a session is blocked while any conflict exists or the session has zero examinees, and the blocking report lists exactly what must be fixed. Once published, each student sees their personal exam table on their dashboard — course, weekday, date, time, venue, committee, seating number — ordered chronologically, with a printable view. Any edit to a published session or distribution returns it to draft (or requires an explicit re-publish), so students never see half-applied changes. This mirrors the old student home page table, but from one validated source behind an explicit publish gate.

**Why this priority**: The publish gate is the safety valve that makes the whole feature trustworthy — the legacy system exposed every raw upload to thousands of students instantly. It ranks P3 because it only becomes meaningful once sessions (US1) and distributions (US2) exist.

**Independent Test**: With a draft term schedule, confirm a student sees no exams; publish; confirm the student sees the complete table including committee and seating number; edit a published session's time; confirm it reverts to draft and disappears from the student view until re-published; attempt publish with a seeded student conflict; confirm it is blocked with the conflict report.

**Acceptance Scenarios**:

1. **Given** a session is in draft state, **When** a student opens their dashboard, **Then** that exam is not shown.
2. **Given** the student's exams for the term are published, **When** the same student opens their dashboard, **Then** they see every published exam of their approved registrations for that term with date, weekday, time, venue, committee, and seating number, ordered by date and time.
3. **Given** a student conflict exists between two sessions, **When** the admin attempts to publish, **Then** publishing is refused and the report names the affected students and course pairs.
4. **Given** a session has zero examinees (all registrations pending/rejected), **When** the admin attempts to publish it, **Then** publishing that session is refused and the board shows it with "0 examinees".
5. **Given** a published session, **When** the admin edits its date or time, **Then** the session returns to draft and the change is invisible to students until re-published.
6. **Given** a staff member without the publish permission, **When** they trigger publish directly, **Then** 403.

---

### User Story 4 - Attendance sheets (Priority: P4)

For exam-day operations, the admin prints an attendance sheet per committee — seating number, student code, name, section — generated from exactly the same data the student sees, and re-printable whenever the distribution changes. Students can likewise print their personal exam table. Both use the browser's print flow, consistent with existing printable pages in the product.

**Why this priority**: Pure operational convenience on top of US1–US3; valuable on exam day but adds no new guarantees.

**Independent Test**: With a published distribution, open a committee's attendance sheet and confirm every placed student appears with the same seating number and committee shown on those students' personal tables; change the distribution, regenerate, and confirm the sheet reflects the new state.

**Acceptance Scenarios**:

1. **Given** a committee with placed students, **When** the admin opens its attendance sheet, **Then** it lists seating number, code, name, and section for exactly the students in that committee.
2. **Given** a student's personal exam table, **When** the student prints it, **Then** the printed page matches what is shown on screen.

---

### Edge Cases

- A course whose registrations are all pending/rejected appears on the board with "0 examinees" and its session cannot be published.
- Two exams for the same student on the same day without time overlap (9–11 and 12–2) are allowed; there is no per-day exam cap in this phase.
- Editing a session's date/time after distribution keeps the distribution but returns the session to draft and re-triggers conflict checking.
- A new approved registration after distribution flags the session "distribution out of date"; regeneration places the new students without moving any already-placed student.
- A rejected/cancelled registration after distribution is removed on the next regeneration; previously printed attendance sheets are simply re-printed.
- A committee capacity reduced below its current placement requires regeneration; no automatic removal of students.
- A second-sitting (resit) exam for the same course in the same semester is allowed because the type is part of the uniqueness rule.
- Semesters are independent: no cross-semester conflict checking (e.g., summer term after second term) — documented as a deliberate decision.
- Two courses sharing the same code in different specializations are unambiguous because sessions attach to the course itself, not its code string.
- Soft-deleted or transferred students are excluded from the examinee audience because the audience derives from active approved registrations only.
- A semester with no configured exam window accepts any session date; configuring a window afterwards constrains new and edited sessions but does not retroactively invalidate already-saved sessions.
- Concurrent publishing while an edit is in flight: the publish gate re-checks conflicts at publish time, so a conflicting state can never become visible to students.
- Two admins submit overlapping sessions for the same venue or shared students at nearly the same moment: the rules are re-checked at save time, so exactly one succeeds and the other is rejected with the normal conflict message — the schedule never ends up in a conflicting state.

## Requirements *(mandatory)*

### Functional Requirements

#### Sessions & conflicts

- **FR-001**: System MUST let authorized staff choose an academic year and semester (defaulting to the current ones) and MUST list, for that term, exactly the courses that have approved registrations — with each course's session state (not set / draft / published) and department/level filters.
- **FR-002**: System MUST allow creating, editing, and deleting an exam session defined by course + academic year + semester + exam type, with date, start time, end time, and optional notes; the combination of course+year+semester+type MUST be unique.
- **FR-003**: A session's end time MUST be later than its start time. Each academic year+semester MUST support a configurable exam window (from/to dates). When a window is configured, a session's date MUST fall inside it and a session dated outside MUST be rejected with a clear Arabic message naming the allowed range; when no window is configured, any date is accepted.
- **FR-004**: The examinee audience of a session MUST be derived solely from students holding an approved registration for the same year+semester that includes the course — no other source of attendance is recognized.
- **FR-005**: System MUST reject saving a session that overlaps in time (same date, `start < other.end && end > other.start`) with another session whose audience shares at least one student; the rejection MUST name the affected students and both courses.
- **FR-006**: System MUST reject saving a session that overlaps in time on the same date with another session using the same venue or the same committee.
- **FR-007**: Session exam types MUST be Regular, Second-sitting (resit), and Improvement from the start — the uniqueness rule (FR-002) allows one session per course+year+semester for each type; using a type is optional per course (activation per need).
- **FR-008**: Conflict checks MUST be scoped within the same academic year and semester; sessions in other terms never block a new session.
- **FR-009**: Editing a session MUST be subject to the same uniqueness, time, and conflict rules, with the edited session excluded from its own conflict detection.
- **FR-010**: System MUST enforce the uniqueness, time-window, and conflict rules atomically at save time so that concurrent submissions cannot produce a conflicting or duplicated schedule; when two overlapping sessions are submitted at nearly the same moment, the later one is rejected with the standard conflict message and the earlier one persists.

#### Committees, distribution & seating

- **FR-011**: System MUST let the admin define one or more committees per session, each with a venue (from the shared venue catalog), a name, and a seat capacity.
- **FR-012**: System MUST be able to automatically distribute a session's examinees across its committees in a stable order (alphabetical by student name), placing each student in exactly one committee and assigning a seating number unique within that committee, never exceeding any committee's capacity.
- **FR-013**: System MUST reject saving or publishing a distribution whose examinee count exceeds the total capacity of the session's committees, stating both numbers.
- **FR-014**: Seating numbers MUST be unique within a committee; numbering is sequential within the committee and the format is configurable (the legacy five-digit level-prefixed rule is not enforced in this phase).
- **FR-015**: Automatic regeneration MUST keep existing placements (including manual moves), remove students who are no longer eligible, and place only students not yet placed; regenerating the same audience against the same placement state MUST produce an identical result.
- **FR-016**: System MUST show the full distribution per committee (seating number, student code, name, section) and MUST let the admin move a student between committees manually.
- **FR-017**: System MUST flag a session "distribution out of date" when a new approved registration for its course appears after distribution, and MUST require regeneration before the session can be (re-)published; regeneration MUST place new students and remove rejected/cancelled ones without disturbing existing placements.
- **FR-018**: Reducing a committee's capacity below its current placement MUST require regeneration and MUST NOT silently remove students from the distribution.

#### Publishing & visibility

- **FR-019**: Publishing and unpublishing MUST apply per session: a session and its distribution MUST be invisible to students until that session is individually published; publishing a session MUST be refused while any student or venue/committee conflict involves it or it has zero examinees, with a report identifying what blocks publishing. A term may contain a mix of published and draft sessions.
- **FR-020**: Editing a published session or its distribution MUST return it to draft state (or require an explicit re-publish); students MUST never see a partially applied change.
- **FR-021**: System MUST provide each student a personal exam table for published sessions of their approved registrations — course, weekday, date, start/end time, venue, committee, seating number — ordered chronologically, on their dashboard and as a printable page.
- **FR-022**: System MUST provide a printable attendance sheet per committee (seating number, student code, name, section) generated from the same data shown to students.

#### Safety & access

- **FR-023**: System MUST block deletion of a course, academic year, venue, or session that has an active distribution until the dependent sessions are handled first, consistent with the existing deletion-guard behavior for academic data.
- **FR-024**: All exam capabilities MUST be gated by dedicated exam-schedule permissions (view, create, edit, delete, publish) enforced end to end — page access and every action — with unauthorized direct access denied (403), not merely hidden; exam navigation entries MUST appear in the admin sidebar under the exams/courses grouping.
- **FR-025**: All user-facing messages (rejections, conflict reports, statuses) MUST be in Arabic, consistent with the rest of the product.

### Key Entities

- **Exam Session**: One sitting of one course's exam — course + academic year + semester + exam type (unique combination), date, start/end time, notes, lifecycle state (draft / published), and a "distribution out of date" flag. Has many committees.
- **Exam Committee**: A named room allocation for a session — venue reference (shared catalog with lecture scheduling), name, seat capacity. Has many seating assignments.
- **Seating Assignment**: A student's placement for a session — committee, seating number unique within the committee. Derived from the examinee audience; regenerated idempotently.
- **Examinee Audience** (derived, not stored): The set of students with an approved registration for the session's year+semester including the course; the single source of truth for who sits the exam.
- **Exam Window**: Per academic year+semester configuration — optional from/to dates bounding valid session dates; enforced for new/edited sessions only when set (FR-003).
- **Existing entities relied upon**: Course, Registration/RegistrationCourse (audience source), Academic Year, Semester, Venue (shared with lecture scheduling), Student (name/section/code for distribution and sheets).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A complete term exam schedule (all sessions, committees, and seating for every course with approved registrations) is prepared in hours of admin time instead of the roughly one week the manual/legacy process takes, with no mandatory spreadsheet upload.
- **SC-002**: Zero published schedules contain a student with two overlapping exams, or a venue/committee double-booked at overlapping times — enforced at save and re-checked at publish.
- **SC-003**: 100% of students with an approved registration in a published term can see the date, time, venue, committee, and seating number of every one of their exams from their own dashboard, without any paper announcement.
- **SC-004**: Printed committee attendance sheets match what students see on their personal tables exactly (same single source) — zero discrepancies between the two views.
- **SC-005**: Nothing reaches students before publish: a draft or mid-edit schedule is visible to zero students (0 leaked records).
- **SC-006**: Regenerating a distribution is trustworthy for correction workflows: the same audience against the same placement state always yields identical results, and no manually adjusted student is ever moved — admins can fix and re-run without surprises.

## Assumptions

- The examinee audience is exactly the approved registrations for the chosen year+semester (BR-2 of the source proposal); no separate attendance source exists.
- Seating numbers are sequential within a committee with a configurable format; the legacy "5 digits starting with the level number" rule is preserved as a possible format, not enforced (source Q2 default).
- No maximum-exams-per-day rule in the first phase — only overlap prevention (source Q5 default).
- Committees and venues use the same venue catalog as the lecture-scheduling feature; if that feature is not delivered first, this feature includes the venue catalog as a dependency (source Q6 default).
- Automatic distribution is order-based (stable alphabetical split respecting capacities), not an optimal constraint-solving allocation; a perfect auto-scheduler is explicitly out of scope.
- Semesters are treated independently for conflict purposes (no cross-semester checks).
- Out of scope for this feature: grade recording (already exists elsewhere in the system and is untouched), invigilator/head-of-committee assignment, online exam question portals, cross-faculty/branch scheduling, the academic-advisor view of exam tables (phase 2), and legacy-format file import of committees/seating — automatic generation alone for launch, with import deferred to a later phase (clarification Q3, 2026-09-08).
- Publishing is per session (clarified 2026-09-08): students see exactly the published sessions of their approved registrations; a term may mix published and draft sessions during preparation, and late sessions (e.g., resits) publish independently.
- UI follows the product's existing Arabic-first, RTL conventions; interactions (toasts, confirmations) mirror existing admin screens.
