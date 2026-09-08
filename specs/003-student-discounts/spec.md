# Feature Specification: Student Discounts

**Feature Branch**: `003-student-discounts`

**Created**: 2026-09-08

**Status**: Draft

**Input**: User description: "Read Student Discounts — Feature Spec (What & Why).md" (Arabic proposal document defining first-class student discounts for the Credit Hour System, replacing the legacy free-text discount rows)

## Clarifications

### Session 2026-09-08

- Q1 (from source doc): May a fixed discount be consumed partially across several fee tickets? → **Decided**: Yes — a fixed discount carries a remaining balance that decreases across tickets until exhausted.
- Q3 (from source doc): Does a zero-net ticket auto-close or need cashier confirmation? → **Decided**: Manual cashier confirmation without a ministerial receipt number; once confirmed it counts as fully paid for all end-of-term logic.
- Q5 (from source doc): Where do legacy "wallet gift balance" entries go? → **Decided**: Wallet deposit flow only — never modeled as a discount.
- Q2 (from source doc): How does a percentage discount apply across tickets? → **Decided**: It applies once to every eligible ticket within its scope — inherently per-ticket, no balance pool.
- Q4 (from source doc): Is second-person approval required before a discount activates? → **Decided**: No approval workflow in v1 — decision reference number plus recorded granter is sufficient.
- Q6 (from source doc): Do discounts cover wallet-settled credit-hour course fees? → **Decided**: No in v1 — discounts apply to issued fee tickets only.
- Q: If a discount was partly applied to a now-paid ticket but still has unused balance, can that remainder be revoked? → A: Yes — recorded applications on paid tickets are immutable, but the unused remaining balance can still be revoked.
- Q: Can the issuer choose NOT to apply an eligible active discount when issuing a ticket? → A: No — eligible discounts always apply automatically at issuance; there is no per-ticket decline.
- Q: Should discounts support an explicit expiry date? → A: No — year/semester scope is the only time boundary; open-scope discounts stay active until exhausted or revoked.
- Q: Does bulk CSV import support percentages and year/semester scoping? → A: No — fixed amounts on the current term only, mirroring the legacy 4-column format.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Grant a discount and see it applied automatically on the fee ticket (Priority: P1)

A finance manager or student-affairs officer grants a student a documented discount — scoped to registration fees, a specific additional fee, or any fee, optionally bound to an academic year and semester — with a value (fixed amount, e.g. 500 EGP, or a percentage, e.g. 50%), a reason, and a decision reference number. From that moment, whenever a fee ticket is issued for that student within the discount's scope, the system detects the eligible active discounts automatically and shows the ticket as three lines: original amount, discount, net. The cashier collects the net, and the printed receipt spells out the discount and its reason, so the student never wonders "why did I pay this amount?".

**Why this priority**: This is the feature's core promise and the gap the legacy system could not fill at all in its current form: a discount that exists as a record, applies itself at issuance, and is transparent at payment. Without it, nothing else (lifecycle, reports, import) has meaning.

**Independent Test**: Provision a student with an active fixed discount and issue a registration-fee ticket larger than the discount; verify the ticket stores original/discount/net, the payment screen and printed receipt show all three lines, and payment is confirmed against the net. Repeat with a percentage discount on a smaller fee. Fully testable without reports or CSV import.

**Acceptance Scenarios**:

1. **Given** student "E123456" has an active fixed discount of 500 EGP on registration fees for semester 1 of 2025/2026, **When** an admin issues a registration-fee ticket of 1,200 EGP, **Then** the ticket shows original 1,200 / discount 500 / net 700, and the discount becomes fully consumed (balance 0).
2. **Given** a 50% discount on the "student card" additional fee (300 EGP), **When** its ticket is issued, **Then** the net is 150 EGP and the discount amounts are frozen in the ticket even if the underlying fee is later changed.
3. **Given** a discount of 1,000 EGP on a 1,000 EGP fee, **When** the zero-net ticket reaches the cashier, **Then** payment is recorded via manual confirmation without any ministerial receipt number, annotated "full discount".
4. **Given** the zero-net ticket from scenario 3, **When** any outstanding-balance check, registration gate, or "finance completed" determination runs afterward, **Then** the ticket counts as fully paid and never blocks the student.
5. **Given** a staff member without the discount-create permission, **When** they attempt to open the discounts screen or grant a discount directly, **Then** access is denied (403), not merely hidden.

---

### User Story 2 - Control the discount lifecycle: partial use, ordering, cancellation (Priority: P2)

Discounts live through a governed lifecycle: active → partially applied → exhausted, or revoked with a stated reason. A fixed discount can be drawn down across several tickets; when two discounts hit the same ticket they apply oldest-first until the ticket's original value is used up. A pending ticket that existed before a discount was granted is never silently rewritten — the admin is offered an explicit "apply to existing ticket" action that re-prices it and records the change, or may cancel and re-issue the ticket. Applications already recorded against a paid ticket can never be undone — corrections there go through a documented manual settlement — though a discount's unused remaining balance stays revocable.

**Why this priority**: The legacy system's worst failures were destructive side effects (deleting tickets, resetting payments when a discount was added) and double-spending of the same discount across recomputations. This story makes the P1 mechanic safe under real-world messiness, but P1 still delivers standalone value for the simple single-ticket case.

**Independent Test**: Grant a 300 EGP discount and issue two 200 EGP tickets: the first consumes 300 (or 200 leaving 100), the second sees only the remainder; two simultaneous issuance attempts against one discount result in exactly one application. Issue a pending ticket, then grant a discount, and confirm nothing changes until the explicit re-pricing action is used. Attempt to revoke a discount on a paid ticket and confirm rejection.

**Acceptance Scenarios**:

1. **Given** an active fixed discount of 300 EGP and a pending ticket of 200 EGP, **When** the discount is applied, **Then** the ticket nets 0 EGP of that fee via a 200 EGP application, the discount stays active with 100 EGP remaining for a later ticket (partial application, not rejection).
2. **Given** two active discounts (registration-fee-scope 400 EGP + exceptional-decision 300 EGP) and a 500 EGP ticket, **When** it is issued, **Then** both apply oldest-first capped at the ticket's original 500 EGP — total discount never exceeds the original amount.
3. **Given** a pending 1,200 EGP ticket issued before a 500 EGP discount exists, **When** the admin adds the discount, **Then** nothing changes automatically and the system offers "apply to existing ticket", which re-prices it to net 700 and logs the change.
4. **Given** a discount partially applied (200 of 500 used), **When** the admin revokes it with a reason, **Then** only the 300 remaining is cancelled; the recorded 200 application stays as history.
5. **Given** a discount fully consumed by an application on a paid ticket, **When** the admin tries to revoke it, **Then** the attempt is rejected with a message pointing to manual settlement — nothing of the paid application can be undone.
6. **Given** two concurrent issuance attempts applying the same 300 EGP discount, **When** both run, **Then** exactly one succeeds and the other sees remaining balance 0 and applies nothing.
7. **Given** a student who is soft-deleted or transferred out, **When** that status change happens, **Then** their active discounts are automatically revoked and the system-actor revocations appear in the discounts screen's audit history — no orphaned financial records.
8. **Given** a 500 EGP discount with 200 EGP applied to a ticket that is now paid and 300 EGP unused, **When** the admin revokes it with a reason, **Then** the 300 EGP remainder is cancelled while the 200 EGP paid application stays untouched and payable-correct.

---

### User Story 3 - Audit and report every EGP discounted (Priority: P3)

A director or auditor opens the discounts report for a period, department, study year, or individual student and sees every discount: who received it, its type, the amount actually applied, the reason, the decision reference, who granted it, and when it was applied. The student financial statement shows four numbers side by side — original dues, total discounts, paid, remaining — and the daily payments record reflects the discount line. Every grant, edit, cancellation, and application carries a full actor-and-timestamp audit trail.

**Why this priority**: Governance is the reason discounts were banned from the current system ("the admin just looks the other way — no record"). Value depends on P1/P2 producing the data, but a finance department will not adopt the feature without it.

**Independent Test**: After granting, applying, and revoking a handful of discounts, open the report filtered by term and department; verify each row's figures match the tickets' stored discount lines exactly (derived from tickets, never recomputed), and that the student statement's four totals reconcile.

**Acceptance Scenarios**:

1. **Given** several discounts granted and applied across the current term, **When** the director runs the term's discounts report, **Then** each row shows student, type, applied amount, reason, decision number, granter, and application date.
2. **Given** a student with discounts applied to issued tickets, **When** their financial statement is opened, **Then** it shows original dues, total discounts, paid, and remaining — consistent with the tickets themselves.
3. **Given** any discount mutation (grant/edit/revoke/apply), **When** the audit history is inspected, **Then** the acting user and timestamp are recorded for each event.

---

### User Story 4 - Bulk discount import from CSV (Priority: P4)

At the start of a term the admin uploads a CSV with columns [student code, fee type, discount amount, discount reason] to grant many fixed-amount discounts at once for the current term (percentages and explicit year/semester scoping are not part of the file format). Header naming is tolerated (spelling variants of the legacy files are accepted), but the content is strictly validated: an unknown student code, a negative amount, or any invalid row rejects the entire file with a clear per-row error report — nothing is half-imported.

**Why this priority**: A convenience carried over from the legacy workflow; the single-grant screen (P1) covers all cases, so this accelerates volume without being essential.

**Independent Test**: Import a valid 5-row file and confirm 5 discounts exist; import a file with one bad row and confirm zero discounts are created and the error names the row and column.

**Acceptance Scenarios**:

1. **Given** a CSV with 5 valid rows, **When** imported, **Then** 5 active discounts exist with the granter recorded.
2. **Given** a CSV where row 3 references a nonexistent student code, **When** imported, **Then** the whole file is rejected and the error identifies row 3.

---

### User Story 5 - Carry over unconsumed legacy discounts (Priority: P5)

An optional console routine imports still-valid discount rows from the legacy `students_discounts` table into the new discount records, so students mid-remediation do not lose entitlements at cutover. Legacy "wallet gift" type rows are excluded (they belong to the wallet, per Q5).

**Why this priority**: Migration nicety for go-live only; the feature is fully functional for new discounts without it.

**Independent Test**: Seed legacy rows (study, administrative, wallet types) in a test dataset, run the import, and confirm only eligible unconsumed study/administrative discounts appear as new records with provenance marked "migrated".

**Acceptance Scenarios**:

1. **Given** legacy discount rows including wallet-type ones, **When** the migration command runs, **Then** only non-wallet, unconsumed rows become active discounts; wallet rows are skipped with a report.

---

### Edge Cases

- **Discount larger than the ticket** → partial application: the available portion applies and the remainder stays active for the next eligible ticket (never the legacy "must equal the full fee" rejection).
- **Two discounts on one ticket** → applied oldest-first, total discount capped at the ticket's original amount.
- **Pending ticket predates the discount** → no silent mutation; explicit re-pricing action or admin cancel-and-re-issue.
- **Partial payment then new discount** → tickets are binary (pending/paid/cancelled — no partial payment exists), so the "unpaid remainder" of a pending ticket is its full amount; paid tickets are never touched.
- **Underlying fee edited or deleted after issuance** → the ticket's stored original/discount/net snapshot is unaffected.
- **Concurrent issuance for the same student** → row-level protection on the discount guarantees it cannot be overspent.
- **Percentage on a zero-value fee** → no application; a notice is logged.
- **Percentage scoped to multiple fees** → the percentage reduces each eligible ticket independently (FR-006).
- **Rounding** → all money is two-decimal; percentage results round half-up; no floating-point storage.
- **CSV with unknown code or negative amount** → whole file rejected with row-level errors (strict, clean validation).
- **Zero-net ticket at term end** → counted as fully paid everywhere outstanding/payment logic applies; the student is never blocked by it.

## Requirements *(mandatory)*

### Functional Requirements

#### Granting & modeling

- **FR-001**: System MUST provide a discounts management screen allowing staff to search a student by code or name and grant a discount specifying: scope (registration fees / a specific additional fee / any fee), optional academic year and semester, value as either a fixed amount or a percentage, a reason, and a decision reference number. There is no separate expiry date — year/semester scope is the only time boundary, and an open-scope discount stays eligible until exhausted or revoked.
- **FR-002**: System MUST model each discount as a first-class record owned by a student — never a free-text row detached from fees — with a type, scope, value, reason, decision reference, granter, and timestamps.
- **FR-003**: System MUST maintain each discount's state as one of: active, partially applied, exhausted, revoked; transitions MUST follow the lifecycle (active → partially applied → exhausted, or any non-exhausted state → revoked with a mandatory reason). Permanent deletion MUST NOT be offered.
- **FR-004**: System MUST keep "gift balance for the wallet" out of the discount concept entirely; such grants go through the existing wallet-deposit flow with its own audit trail.

#### Application at issuance

- **FR-005**: When a fee ticket is issued, the system MUST automatically detect all active discounts whose scope matches the student, fee category, and (when set) year/semester, and apply them — always, with no per-ticket decline option — storing on the ticket: original amount, total discount applied, and net amount due.
- **FR-006**: A fixed-amount discount MUST be drawable across multiple tickets via its remaining balance until exhausted. A percentage discount MUST apply once to every eligible ticket within its scope, each ticket independently reduced by the percentage (no shared balance pool).
- **FR-007**: The system MUST never allow the sum of discounts on one ticket to exceed that ticket's original amount, and MUST never allow a discount to be applied beyond its own remaining balance.
- **FR-008**: When multiple discounts apply to one ticket, the system MUST apply them oldest-grant-first until the original amount is fully discounted.
- **FR-009**: Percentage values MUST be computed at issuance time and frozen into the ticket; later changes to the underlying fee MUST NOT alter already-issued tickets.
- **FR-010**: The system MUST guarantee that concurrent issuance attempts cannot double-apply the same discount — exactly one application per unit of balance, verified under simultaneous requests.
- **FR-011**: Applying a percentage to a zero-value fee MUST result in no application and MUST record a system-log notice (no user-facing record required in v1).

#### Payment & tickets

- **FR-012**: The payment confirmation screen and the printed receipt MUST show the original amount, the discount (with its reason), and the net; the cashier collects the net.
- **FR-013**: A ticket whose net is 0 (full discount) MUST still be issuable and MUST require explicit manual cashier confirmation to close, bypassing the ministerial-receipt-number check, annotated as a full-discount payment. No auto-closure.
- **FR-014**: Once a zero-net ticket is confirmed, every payment-dependent behavior (outstanding-balance totals, registration eligibility gates, "term finance completed" determinations) MUST treat it as fully paid; it MUST never appear as outstanding nor block the student.
- **FR-015**: If a pending ticket for the same fee exists before a discount is granted, the system MUST NOT silently modify or delete it; it MUST offer an explicit "apply to existing ticket" action that re-prices the pending ticket and records the change, leaving cancel-and-re-issue as the alternative.
- **FR-016**: Applications recorded against a paid ticket MUST be immutable — a discount MUST NEVER be undone, reduced, or re-applied retroactively on a paid ticket; corrections there MUST be handled as documented manual settlement/refund outside this feature.

#### Lifecycle & safety

- **FR-017**: Revoking a partially applied discount MUST cancel only its remaining balance; recorded applications remain as history — even when some of those applications sit on now-paid tickets (FR-016 immutability applies to the applications, not to the unused balance).
- **FR-018**: When a student is soft-deleted or transferred out, the system MUST automatically revoke their active discounts and record the revocation as a system-actor audit event surfaced on the discounts screen (v1 has no external notification channel).
- **FR-019**: All monetary amounts MUST be stored and reported to two decimal places; percentage results MUST round half-up.

#### Governance & reporting

- **FR-020**: Every discount event (grant, edit, revoke, application) MUST record the acting user and timestamp.
- **FR-021**: System MUST enforce distinct permissions for viewing, creating, editing, and revoking discounts; applying discounts at issuance requires no extra permission — it inherits the finance permissions already gating issuance/payment.
- **FR-022**: System MUST provide a discounts report filterable by period, department, study year, and student, showing per-discount: student, type, applied amount, reason, decision number, granter, application date, with totals; figures MUST be derived from stored ticket snapshots, never recomputed.
- **FR-023**: The student financial statement MUST display original dues, total discounts, paid, and remaining; the daily payments record MUST surface the discount line.

#### Import & migration

- **FR-024**: System MUST offer an optional bulk CSV import of discounts with columns [student code, fee type, discount amount, discount reason], creating fixed-amount discounts scoped to the current term only (no percentages, no year/semester columns), tolerating legacy header spelling variants, validating every row, and applying all-or-nothing semantics with row-level error reporting.
- **FR-025**: System MUST provide an optional console routine to import unconsumed legacy discount records as new discounts, excluding legacy wallet-gift rows.

#### Approval

- **FR-026**: A granted discount MUST become active immediately — no second-person approval step in v1; the decision reference number and recorded granter constitute the authorization trail.

#### Scope boundary

- **FR-027**: Discounts MUST apply to issued fee tickets only; credit-hour course fees settled through the student wallet are excluded from discounting in v1.

### Key Entities *(include if feature involves data)*

- **Student Discount**: A documented entitlement granted to one student. Attributes: student, scope (fee category, optional year/semester — the only time boundary, no expiry date), value kind (fixed amount or percentage), amount/percentage, remaining balance (fixed only), reason, decision reference number, state (active / partially applied / exhausted / revoked), granter, revoke reason, timestamps.
- **Discount Application**: The record that a specific discount reduced a specific ticket by a specific amount. Attributes: discount, ticket, applied amount, applying user, timestamp. This is what makes consumption auditable and prevents double-spending.
- **Fee Ticket (extension of existing entity)**: Gains a financial snapshot — original amount, discount amount, net amount — so every downstream report reads the ticket instead of recomputing.
- **Audit Event**: Who did what to which discount and when (grant, edit, revoke, apply).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of discounts in the system carry a complete provenance chain — granter, decision reference, applied-to ticket(s), and timestamps — with no discount requiring cross-screen recomputation to explain.
- **SC-002**: Zero double-spend incidents: no discount is ever applied beyond its balance and no ticket is ever discounted beyond its original value, including under simultaneous issuance attempts.
- **SC-003**: Issuing a ticket for a discount-eligible student takes no extra steps for the cashier compared with issuing one for a student without discounts (fully automatic detection and application).
- **SC-004**: 100% of printed receipts for discounted tickets show the original/discount/net breakdown with the discount reason — eliminating "why did I pay this amount?" disputes at the counter.
- **SC-005**: The term's discounts report is produced in a single action and its totals reconcile exactly with the sum of stored ticket discount lines (0 discrepancy).
- **SC-006**: Students with fully discounted (zero-net) terms are never blocked by payment gates or shown outstanding balances attributable to those tickets.

## Assumptions

- The existing fee-issuance, payment-confirmation, wallet, and student-financial-status flows remain in place; discounts integrate into them rather than replacing them.
- Amounts are in EGP with two-decimal precision, matching existing money conventions in the system.
- Discount granting remains a manual, documented staff action in v1 (rule-based auto-discounts such as honors/sibling/martyr-family quotas are explicitly deferred).
- The textual decision reference number is the v1 paper trail; no electronic approval workflow is built (Q4 decided).
- Legacy `students_discounts` data exists and may be partially carried over via the optional migration routine; wallet-gift rows are re-routed to the wallet, not converted.
- Student-facing visibility of their own discounts is a later phase and excluded here.
- Reporting consumers (student financial status, daily payments) already exist and only need the discount figures surfaced from ticket snapshots.

## Out of Scope

- Automatic/eligibility-rule-driven discount generation (honors students, siblings, etc.) — manual documented granting only in v1.
- Multi-level electronic approval workflow for discounts (Q4 decided: none in v1).
- Editing paid tickets or automated refund flows for revoked discounts (BR-9: manual documented settlement only).
- Student portal display of granted discounts (phase 2).
- Full automated migration of all legacy discount data (limited to the optional console routine, FR-025).
- Discounts on wallet-settled credit-hour course fees (Q6 decided: excluded from v1).
- Explicit discount expiry dates (decided: year/semester scope is the only time boundary in v1).
