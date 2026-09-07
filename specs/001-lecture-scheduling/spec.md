# Feature Specification: Lecture & Venue Scheduling

**Feature Branch**: `001-lecture-scheduling`

**Created**: 2026-09-08

**Status**: Draft

**Input**: User description: "Read Lecture Scheduling — Feature Spec (What & Why).md" (Arabic proposal document defining lecture/venue scheduling for the Credit Hour System)

## Clarifications

### Session 2026-09-08

- Q: Which student count should the capacity check use when validating a session's selected sections? → A: Actual enrolled students per section, falling back to the configured "students per section" value when a section has zero enrollments.
- Q: Should venue and section conflict checks consider only the same academic year and semester, or all schedule records regardless of term? → A: Same academic year and semester only; sessions carry that context from their course.
- Q: When selected sections exceed a venue's capacity, should the save be a final rejection, or a warning an authorized user can push through? → A: Hard reject — over-capacity sessions can never be saved in this phase; any override capability is deferred to a later phase.
- Q: What should "inactive" mean for a venue when scheduling? → A: Inactive venues cannot be picked for new sessions; already-scheduled sessions remain valid and unflagged.
- Q: If two admins save overlapping sessions for the same venue/section at nearly the same moment, what must the outcome be? → A: Absolute guarantee — the second concurrent save is rejected at save time; the schedule can never contain a conflict.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Conflict-free lecture session scheduling (Priority: P1)

A student-affairs admin opens a course (e.g., "Accounting — Arabic specialization — First year — First semester") and records *when* and *where* it is taught: a venue ("Hall A"), a weekday ("Sunday"), a time slot ("9:00 → 10:30"), and which of the course's sections attend — either by ticking sections individually or by the common shorthand "from section 1 to section 10". Before saving, the system checks three things: the venue is not already booked for an overlapping time that day, none of the selected sections has an overlapping class elsewhere, and the total students in the selected sections fit the venue's capacity. Any violation blocks the save with a clear Arabic message naming the conflicting session (course, venue/section, day, time).

**Why this priority**: This is the core of the feature. Today "when and where" lives in Excel/paper with zero guarantees against double-booking or over-capacity rooms; without this story nothing else matters.

**Independent Test**: Pre-provision two venues and a course linked to twelve sections (at the data level), then: (a) create a valid session and confirm it is saved; (b) attempt an overlapping session in the same venue → rejected; (c) attempt a session where a chosen section already has an overlapping class in another course → rejected; (d) attempt a session whose selected sections exceed venue capacity → rejected. All outcomes are verifiable without the other stories' screens.

**Acceptance Scenarios**:

1. **Given** course "Accounting" (Arabic dept., 1st year, 1st semester) is linked to sections 1–12, and "Hall A" holds 300 while sections 1–10 total 280 students, **When** the admin saves a session at Hall A, Sunday 9:00–10:30, sections 1 through 10, **Then** the session is saved and appears in the course's session list (its weekly-grid appearance is validated under User Story 3).
2. **Given** "Hall A" is already booked Sunday 9:00–10:30 by another course, **When** the admin tries to save a new session in the same hall Sunday 10:00–11:30, **Then** the save is rejected with an Arabic message identifying the conflicting course, day, and time.
3. **Given** section 3 already attends another course Monday 12:00–13:30, **When** the admin adds a session including section 3 on Monday 13:00–14:30, **Then** the save is rejected and the conflicting session is named.
4. **Given** "Lab 1" has a capacity of 40 students, **When** the admin selects sections totaling 60 students, **Then** the save is rejected with: "الإجمالي المختار 60 طالب يتجاوز سعة المعمل (40)".
5. **Given** an existing session, **When** the admin edits its time so it would clash with itself, **Then** the edit is allowed (the edited session is excluded from its own conflict check), while a clash with any other session is rejected.
6. **Given** a staff member without the lecture-scheduling create permission, **When** they open the add-session page or trigger the save action directly, **Then** access is denied (403), not merely hidden.

---

### User Story 2 - Venue management (Priority: P2)

The admin registers the rooms the faculty teaches in — a one-off, rare task: a name ("Hall A"), a kind (lecture hall / lab / classroom / other), an official capacity (300 seats), and whether the venue is currently in service. A venue referenced by scheduled sessions cannot be deleted.

**Why this priority**: Venues are the catalog scheduling draws from — nothing can be placed without them — but the rules themselves (US1) carry the value, and venue data is small and stable.

**Independent Test**: Create venues with unique names, kinds, and capacities; edit and deactivate them; attempt a duplicate name → rejected; attempt to delete a venue referenced by a session → blocked with a guard message.

**Acceptance Scenarios**:

1. **Given** no venue named "Hall A" exists, **When** the admin creates it as a lecture hall with capacity 300, **Then** it becomes selectable when scheduling sessions.
2. **Given** a venue named "Hall A" already exists, **When** the admin creates another venue with the same name, **Then** it is rejected with a clear message.
3. **Given** a venue linked to existing lecture sessions, **When** the admin tries to delete it, **Then** deletion is blocked, following the same deletion-guard behavior already applied to courses and sections.
4. **Given** a venue whose capacity is later reduced below what an already-scheduled session needs, **When** the admin reviews the schedule, **Then** the affected session is flagged "over capacity" rather than silently removed.

---

### User Story 3 - Weekly schedule review (Priority: P3)

After sessions are saved, the admin reviews the week: a list of the course's sessions plus a weekly grid (day × time) showing where each class sits, so gaps and oddities are visible at a glance. A venue-centered view of the same grid is a later phase.

**Why this priority**: Review gives the admin confidence that the conflict-free data is also *sensible* (no awkward holes in the week), but it adds no new guarantees beyond US1–US2.

**Independent Test**: With several sessions saved across different days, open the course's weekly grid and confirm every session appears in its correct day/time cell and the list matches the grid exactly.

**Acceptance Scenarios**:

1. **Given** a course with sessions Sunday 9:00–10:30 and Tuesday 11:00–12:30, **When** the admin opens the course's weekly grid, **Then** both sessions appear in their correct day/time cells.
2. **Given** a section was moved to another level/department (or unlinked from the course) after being scheduled, **When** the admin opens the schedule, **Then** the affected session is flagged with a warning and that section cannot be added to any new session.

---

### Edge Cases

- A section with zero enrolled students counts toward capacity using the configured "students per section" value (it may be a newly forming section) and may still be scheduled.
- A venue with no defined capacity skips the capacity check but shows a "capacity not defined" notice.
- Adjacent bookings are allowed: a session ending 10:30 and another starting 10:30 in the same venue do not conflict; any overlap (e.g., 9:00–10:30 vs 10:00–11:00) is rejected.
- The same section may appear in two sessions of the same course at different times (e.g., two lectures per week).
- A reversed range selection ("from 10 to 1") is normalized to 1–10.
- Selecting a section that does not belong to the course's department/level is rejected on the server side, not merely hidden in the interface.
- A section removed from the course (or reassigned to another level/department) after being scheduled leaves the old session in place but flagged; it cannot join new sessions.
- A venue capacity reduced after scheduling does not delete sessions; the review screen flags "over capacity".
- Duplicate section picks (the same section chosen via both checkbox and range) are deduplicated.
- Sessions live within a single day (no midnight-spanning sessions); times are entered to a quarter-hour granularity.
- Two admins submit overlapping sessions for the same venue/section simultaneously: the rules are re-checked at save time, so exactly one succeeds and the other is rejected with the normal conflict message — the schedule never ends up in a conflicting state.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow authorized staff to manage venues, each with a unique name, a kind (lecture hall, lab, classroom, other), an optional official capacity, and an active/inactive status; an inactive venue MUST NOT be selectable for new sessions, while sessions already scheduled in it remain valid and unflagged.
- **FR-002**: System MUST allow authorized staff to create, edit, and delete lecture sessions, each tied to exactly one course and specifying a venue, a weekday, a start time, an end time, and one or more attending sections.
- **FR-003**: A course MUST support multiple sessions (e.g., two lectures per week on different days).
- **FR-004**: Sessions MUST NOT duplicate the course's department, level, or semester; these are derived from the owning course.
- **FR-005**: The sections selectable for a session MUST be restricted to those already linked to that course and belonging to the same department and level.
- **FR-006**: Staff MUST be able to choose attending sections individually and by range ("from section X to section Y"); overlapping picks MUST be deduplicated, and a reversed range MUST be normalized (min/max).
- **FR-007**: System MUST reject a session when the total students across the selected sections exceeds the venue's official capacity, counting each section's actual enrolled students and falling back to the configured "students per section" value when a section has zero enrollments. This rejection is final in this phase — no user may override and save an over-capacity session.
- **FR-008**: System MUST reject any session whose venue is already booked on the same weekday for an overlapping time; back-to-back bookings (one ends exactly when the next starts) are allowed.
- **FR-009**: System MUST reject any session where a selected section already attends an overlapping session on the same weekday, even for a different course in a different venue.
- **FR-010**: A session's end time MUST be later than its start time; time entry granularity is a quarter hour (15 minutes).
- **FR-011**: Schedulable weekdays MUST be limited to Saturday through Thursday (Friday is a holiday).
- **FR-012**: Conflict checks (FR-008, FR-009) MUST be scoped within the same academic year and semester; a new session is stamped with the currently active academic year at save time, and its semester comes from the owning course; bookings in other years/terms never block a new session.
- **FR-013**: Editing a session MUST be subject to the same capacity and conflict rules, with the edited session itself excluded from conflict detection.
- **FR-014**: System MUST prevent deletion of a venue, course, or section while it is referenced by lecture sessions, consistent with the deletion guards already applied to courses and sections.
- **FR-015**: All venue and scheduling capabilities MUST be permission-gated end to end (page access and every action), with distinct view/create/edit/delete permissions for venues and for lecture sessions, and schedule links surfaced in the admin sidebar under the courses section.
- **FR-016**: Every rejection (conflict or capacity) MUST produce a clear Arabic message naming the conflicting venue or section, the day, the time, and the course it conflicts with.
- **FR-017**: While choosing sections, staff MUST see each section's current student count and the running selected total against the venue capacity, updating as the selection changes and before saving.
- **FR-018**: System MUST present, per course, a list of its sessions and a weekly grid (day × time); a venue-centered view is deferred to a later phase.
- **FR-019**: Over-capacity situations arising after the fact (e.g., a venue's capacity reduced below scheduled attendance) MUST be surfaced as a flagged state on the review screen rather than auto-deleting sessions.
- **FR-020**: System MUST enforce conflict and capacity rules atomically at save time so that concurrent submissions cannot produce a conflicting or over-capacity schedule; when two overlapping sessions are submitted at nearly the same moment, the later one is rejected with the standard conflict message and the earlier one persists.

### Key Entities

- **Venue**: A physical room where lectures take place — unique name, kind (hall/lab/classroom/other), optional official capacity, active status; referenced by many lecture sessions.
- **Lecture Session**: A weekly meeting of exactly one course in one venue at one weekday/time slot; carries the active academic year recorded at creation and the semester of its course; includes many sections.
- **Course** (existing): Owner of sessions; supplies department, level, and semester context.
- **Section** (existing): Attends sessions; must be linked to the course and share its department/level; its enrolled-student count drives capacity checks.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of venue double-bookings, section double-bookings, and capacity overruns are caught before saving — no conflicting session can exist in the schedule.
- **SC-002**: Staff can build a full week's schedule for every section of one academic level entirely inside the system, with zero reliance on parallel spreadsheets or paper timetables.
- **SC-003**: Creating a typical session (venue + day + time + section range) takes under one minute, thanks to range selection and live capacity feedback.
- **SC-004**: Every rejection message lets the admin identify and resolve the clash without manual investigation — naming the conflicting course, venue/section, day, and time.
- **SC-005**: Reviewing one course's full weekly schedule for gaps and clashes takes under two minutes via the grid view.
- **SC-006**: The recorded schedule data is complete enough to support later phases (student timetable views, printable exports) without re-collecting any information.

## Assumptions

- The existing first-class Section entity (with department, level, and real enrolled students) is the scheduling basis; the legacy system's dynamically computed section numbers are not reproduced.
- Friday is not a teaching day (Saturday–Thursday only), per the proposal's suggested default.
- No requirement in v1 that a course's total scheduled session time matches its credit hours, per the proposal's suggested default.
- Single, flat venue list for this phase: no branches, buildings, or floor hierarchy.
- Instructor/teaching-assistant assignment is out of scope (no teacher entity exists yet); exam scheduling, automatic scheduling, and student/advisor timetable views are later phases.
- Access control reuses the existing permission system; this feature adds venue and lecture-schedule permissions and sidebar navigation entries.
- Times are stored and compared within a single day, so no timezone or daylight-saving ambiguity arises.
