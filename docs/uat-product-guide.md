# School Management System — Product Overview (Non-Technical)

Use this document for UAT planning, stakeholder onboarding, and AI-assisted issue writing. It describes **what the product does** in business language, without implementation detail.

---

## What this product is

A single online platform that helps **schools run day-to-day operations in one place**: student records, classes, attendance, exams and report cards, fee tracking, boarding/hostel, kitchen and store inventory, and parent communication.

It is built for **East African schools first** (Tanzania focus): fees in **TZS**, support for **English and Swahili**, and workflows that match how schools actually collect fees (bank deposits, mobile money, cash — with proof on paper or screenshot, not through an in-app payment gateway).

One company or group can operate **many schools** on the platform. Each school's data stays completely separate from others. A school group with more than one campus can also keep each campus's data separate within their account.

**Important finance principle:** the system **records and verifies payments** that parents already made outside the app. It does **not** move money, charge cards, or connect to mobile-money APIs in version 1.

---

## Who uses it

| User | What they mainly do |
|------|---------------------|
| **Platform operator** | Creates new school accounts, monitors activity across schools, can sign in as a school user to help with support |
| **School group admin** | Sets up the organisation: branding, settings, users, which menus each role sees |
| **School admin** | Runs one school: users, classes, sessions, overall oversight |
| **Academic director** | Approves and publishes exam results |
| **Teachers / class teachers** | Take attendance, set homework, enter marks for their classes |
| **Finance staff** | Review payment slips parents submit, approve or reject them, issue receipts |
| **Hostel manager** | Manage dormitories, room assignments, meal plans, approve student leave |
| **Storekeeper** | Manage stock, issue items to the kitchen, raise purchase requests |
| **Kitchen staff** | Request ingredients/supplies from the store |
| **Parents / guardians** | See their children's fees, attendance, and results; upload proof of payment |
| **Students** | *Not available yet* — there is no student login portal in the current version |
| **Auditor** | Read-only access to financial and academic records for review |

Each person sees only what their job allows. A parent never sees another family's children. A teacher only works with their assigned classes. Finance staff handle payments; teachers cannot approve them.

---

## Problems the product solves

1. **Scattered records** — student, academic, and fee information in one system instead of spreadsheets and paper.
2. **Slow fee reconciliation** — parents submit deposit slips online; finance verifies against school records and issues official receipts.
3. **Limited parent visibility** — guardians can check balances, attendance, and published results without visiting the school.
4. **Weak audit trail** — important actions (admitting a student, verifying a payment, publishing results) are logged for accountability.
5. **Boarding complexity** — room allocation and leave requests tied to the same student and fee picture.

---

## Main areas of the product

### 1. School setup and administration

School leaders configure:

- School name, code, and basic settings
- Academic year (e.g. 2026/2027)
- Classes (e.g. Form 1, Form 2) and subjects
- Which staff teach which class and subject
- User accounts and roles (who is a teacher, parent, finance officer, etc.)
- Optional rules, such as blocking report cards when fees are unpaid

**Done when:** a new school can be set up and staff can log in with the right access, without developer help.

---

### 2. Student records (admissions and progression)

Schools can:

- **Admit** new students (day student or boarding)
- Place them in a **class** for the current academic year
- Link **parents/guardians** (one parent can have several children; one child can have several guardians)
- **Promote** students to the next class or year — old records are kept; nothing is erased
- **Import** many students at once from a spreadsheet template
- View and export student lists

**Done when:** a student can be admitted, assigned to a class, linked to a guardian, and promoted while full history remains visible.

**Not in this version:** attaching document files to student profiles; full timetable with clash checking.

---

### 3. Homework and class work

Teachers can:

- Create assignments for their classes
- Save as **draft** (only staff see it) or **publish** (parents and enrolled students' families see it)

**Done when:** a published assignment appears for the right class; drafts stay hidden from parents.

**Not in this version:** students submitting work online or teachers grading submissions in the app (only the listing side exists).

---

### 4. Attendance

Teachers record who was present, absent, late, or excused for their classes.

- Records can be updated in a batch for a whole class
- Parents can see their child's attendance history on the child's page

**Done when:** attendance taken in class shows correctly for staff and for the linked parent.

**Not in this version:** automatic SMS or email when a child is absent.

---

### 5. Exams, marks, and report cards

The flow is deliberate and controlled:

1. School defines **assessments** (e.g. tests, exams) and how much each counts toward the final grade.
2. Teachers **enter marks** only for subjects and classes they teach.
3. An **academic director** (or equivalent senior role) **publishes** results — until then, results are not final for parents.
4. Once published, results **cannot be changed in place**; corrections would require a new versioned record (audit-friendly).
5. The system can **generate report card PDFs** for a class.
6. Optionally, the school can **block report cards** if the student still owes fees above a set threshold.

**Done when:** marks from several teachers roll up into an approved, published report card that parents can view, with a downloadable PDF.

**Limitations today:** report card design uses placeholder branding, not the school's full letterhead; letter grades from a custom grading scale may not appear on the PDF yet.

---

### 6. Fees and payments (record, don't pay)

This is one of the most important modules.

#### How fees work

- The school defines **fee structures** (e.g. tuition, hostel, activity) per class and academic year.
- Each student has a **fee account** showing what is owed, what was paid, discounts (when added later), and balance.
- Currency is **TZS**; amounts use normal money precision (two decimal places).

#### How parents pay (outside the system)

Parents pay via bank, mobile money, or cash **in the real world**. They then **upload proof** in the app:

- Photo or PDF of the deposit slip
- Amount paid
- Date of payment
- Bank/teller/reference details
- **Split** of the payment across fee types (e.g. half tuition, half hostel) — the parts must add up to the total

The system assigns a **slip reference number** (e.g. SLP-…) and puts the slip in a **pending** queue.

#### How finance staff handle slips

Finance staff open a **verification queue**, review each slip, and either:

- **Approve** — balance updates, a **receipt** is issued (with its own number, e.g. RCP-…), and the parent can download it
- **Reject** — with a written reason (required); parent can submit a corrected slip

Duplicate slips (same teller/reference on the same day) should be caught. Parents **cannot** approve their own payments.

#### What this module does *not* do

- Process payments inside the app
- Connect to banks or M-Pesa automatically
- Offer discounts, payment plans, or full reconciliation reports yet (planned later)
- A dedicated "view my child's full fee statement" page for staff (balance is visible through slips and parent dashboard for now)

**Done when:** parent submits slip → finance approves → receipt issued → student balance is correct.

---

### 7. Hostel and boarding

For boarding schools:

- Define **hostels** and **rooms** (capacity, boys/girls)
- **Assign** boarding students to rooms for an academic year
- Optional rule: **block room assignment** if hostel fees are still unpaid (school turns this on or off)
- **Meal plans** can be linked to allocations
- **Leave / exeat:** parent requests permission for a student to leave; hostel manager approves or rejects

**Done when:** a boarding student with cleared fees (if the rule is on) gets a room; leave requests follow approve/reject workflow.

**Not in this version:** alerts to hostel manager by SMS; "partial payment — needs review" flag (only fully blocked or allowed today).

---

### 8. Store and kitchen inventory

For schools that run a store and kitchen:

- **Catalog** of items (rice, oil, etc.) with quantities and cost tracking
- **Kitchen staff** submit **requisitions** ("we need 20 kg rice")
- **Storekeeper** can **issue in parts** — e.g. 10 kg today, 10 kg later — stock goes down each time
- **Purchase requests** when stock must be bought; **finance** can approve, adjust quantities, and record what was actually received; stock and average cost update
- **Low-stock warnings** when quantity falls below a reorder level (in the app; not SMS yet)

**Done when:** a cook's request can be partially fulfilled across two handovers with correct stock; a purchase can be amended and received with different quantities than ordered.

---

### 9. Dashboard and reports

- **Staff dashboard:** snapshot counts — active students, today's attendance, pending payment slips, hostel occupancy, current academic year
- **Parent dashboard:** one card per child — class, fee balance, payment status, pending slips
- **Teachers** without dashboard widgets get a simple landing message; their main work is attendance and marks
- **Export:** most lists (students, slips, hostel, inventory, etc.) can download to **Excel or PDF**
- **Import:** bulk add students, subjects, or classes from Excel templates

**Not in this version:** rich analytics charts, scheduled reports, or email delivery of reports.

---

### 10. Parent portal

Logged-in parents can:

- See all their children on the home dashboard
- Open each child's page: **payment slips and receipts**, **attendance**, **published results**
- **Submit new payment slips** with photo and fee breakdown
- Request **hostel leave** where applicable

They **cannot** see other families' data, staff-only controls, or draft/unpublished academic information.

---

### 11. Platform operator (support layer)

A separate **platform admin** login (not tied to one school) can:

- **Create** a new school account (with first admin user)
- Browse a **central activity log** of important changes across schools
- **Impersonate** a school user to troubleshoot (with a clear banner to return to platform view)

**Not in this version:** deleting schools from the UI; cross-school statistics dashboard.

---

## Typical journeys (how work flows day to day)

### New student joins mid-year

Admin admits student → assigns class and day/boarding → links parent account → parent logs in and sees the child → finance sets or inherits fee structure → parent pays at bank and uploads slip → finance verifies → receipt and updated balance.

### End of term academics

Teachers enter marks → academic director publishes → parents see results → school generates report cards → if fee gate is on, students with large balances may be blocked until fees are cleared.

### Boarding student needs to go home

Parent submits leave request → hostel manager approves → record kept for audit.

### Kitchen runs low on supplies

Kitchen submits requisition → storekeeper issues what is available → if more is needed, storekeeper raises purchase request → finance approves and records delivery → stock and costs update.

---

## What is intentionally out of scope (version 1)

| Out of scope | Why |
|--------------|-----|
| Paying inside the app | Schools collect money externally; app only records proof |
| Student login portal | Product decision pending; students don't sign in yet |
| Payroll / HR | Different product area |
| Full timetable | Deferred |
| SMS / email alerts | Provider not chosen; notifications not live |
| Library, transport GPS, full LMS | Future phases |
| Biometric hardware | Not integrated |

Issues about these should be logged as **future enhancements**, not bugs, unless the business decides to include them now.

---

## Rules the product must always respect

These are business rules testers should validate:

1. **Privacy between schools** — no user ever sees another organisation's students, fees, or marks.
2. **Privacy between campuses** — within one organisation, Campus A staff don't access Campus B data unless their role allows it.
3. **Parent scope** — parents only see their own linked children.
4. **Role boundaries** — teachers don't verify payments; parents don't publish results; kitchen staff don't approve purchases (finance does).
5. **Published results and verified payments are serious records** — they shouldn't be silently edited or deleted; history matters for disputes and audits.
6. **Fee math is authoritative** — balances shown in the app should match what finance expects; a parent's split of a slip must equal the total they claim to have paid.
7. **Draft vs published** — homework and results hidden until officially published.

---

## How to test it

Use the **demo school** after local setup. Default password for seeded accounts: **`password`**.

| Email | Role |
|-------|------|
| `admin@demo.sms.test` | School group admin |
| `school-admin@demo.sms.test` | School admin |
| `grace.mwangi@demo.sms.test` | Class teacher |
| `daniel.kessy@demo.sms.test` | Academic director |
| `amina.hassan@demo.sms.test` | Finance manager |
| `joseph.mollel@demo.sms.test` | Accountant |
| `esther.nyerere@demo.sms.test` | Hostel manager |
| `john.mwanga@demo.sms.test` | Storekeeper |
| `neema.saidi@demo.sms.test` | Kitchen staff |
| `peter.mushi@demo.sms.test` | Teacher |
| `parent.####@demo.sms.test` | Parent (suffix varies per seed run) |
| `platform-admin@sms.test` | Platform operator (separate from any school) |

**Highest-priority UAT areas:**

1. Parent submits payment slip → finance approves → balance and receipt correct
2. Parent only sees their children everywhere
3. Unpublished marks/assignments hidden from parents
4. Promoting a student keeps old year/class history
5. Hostel room rules (capacity, gender, optional fee block)
6. Store requisition partial issue and stock levels
7. Platform admin can create a school and support staff via impersonation

**Short smoke pass:** log in as each main role → do one core task per module → try something you're not allowed to do and confirm access is denied.

---

## Writing UAT issues (template)

```markdown
## Title
[Module] Short description — role affected

## Environment
- Demo school (or staging URL)
- User role and account used
- Browser / device

## Preconditions
What must already exist (student admitted, fee structure set, etc.)

## Steps to reproduce
1. Log in as …
2. Go to …
3. Do …

## Expected result
What should happen (refer to section above if helpful)

## Actual result
What happened instead

## Severity
Blocker | Critical | Major | Minor | Enhancement

## Out of scope?
Yes/No — is this a known v1 gap?
```

### Severity guide

| Severity | Examples |
|----------|----------|
| **Blocker** | Cannot log in; see another family's child; approved payment doesn't update balance |
| **Critical** | Parent sees unpublished results; teacher approves payments; promotion erases history |
| **Major** | Export missing rows; report card never generates; import drops valid records |
| **Minor** | Wrong label; awkward screen flow; generic report card layout |
| **Enhancement** | Student portal; SMS alerts; payment plans |

---

## Success criteria (when the product is "ready enough")

- [ ] A school can be configured and used without engineering support
- [ ] Full student journey: admit → class → promote with history
- [ ] Attendance and marks through to published report cards
- [ ] Fee slip → verification → receipt → correct balance
- [ ] Parents see only their children; staff see only what their job allows
- [ ] Boarding allocation and leave workflows work
- [ ] Store requisition and purchase flows keep stock accurate
- [ ] Important actions appear in audit/history where implemented
- [ ] Exports match what each role is allowed to see on screen

---

## Known gaps and rough edges

- **No student app/login** yet
- **No automatic text messages or emails** for absences, payments, or results
- **Teacher picker** in some admin screens may ask for internal user IDs instead of a friendly name search
- **Report cards** may look generic, not fully branded
- **No payment plans, scholarships, or finance reconciliation reports** yet
- **No dedicated student fee statement page** for staff beyond what slips and dashboard show
- **Manual browser testing** of the full payment journey is still recommended

---

## Summary

This is a **school operations hub** for East Africa: one place where each school manages students, classes, attendance, exams, fees (by verifying real-world payments, not processing them), boarding, and kitchen/store stock. **Parents** upload payment proof and follow their children's attendance and results. **Staff** work within role limits. **Senior roles** publish results and verify money. **Platform operators** onboard new schools and audit activity. Version 1 is **feature-rich for staff and parents** but **does not yet include student login, automated messaging, or in-app payments** — and several finance and reporting refinements are planned for later releases.

---

## Related documents

| Document | Audience |
|----------|----------|
| `PRD.md` | Full product requirements (more formal) |
| `docs/prd-financial-module.md` | Fee and payment module detail |
| `docs/prd-stores-inventory-module.md` | Store and kitchen module detail |
| `PROJECT-PLAN.md` | Delivery phases and what's done vs deferred |
