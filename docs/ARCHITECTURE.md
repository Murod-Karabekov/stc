# JDPU STC Platform — Architecture and Requirements

## 1. System Overview
JDPU STC (Jizzakh State Pedagogical University Student Technology Club) is a university technology club platform featuring:
1. **Public Homepage**: Premium, minimalist, single-screen retro scientific-terminal aesthetic with dynamic particle system and Web Audio API reactive waveform.
2. **Candidate Selection**: Passwordless, minimal 2-field registration (+998 phone + full name), timed server-authoritative 25-question test across 5 sections, shuffling, snapshot preservation, auto-saving, anti-cheating deterrents, and voluntary/auto submission.
3. **Administrator Panel**: Server-side protected `/admin` backed by EasyAdmin 5 and Symfony Security (ROLE_ADMIN), exam lifecycle management, question authoring with sanitized markdown and safe image uploads, attempt audit & reset, CSV export with formula injection defense, and optional Google Sheets sync.

## 2. Roles and Permissions
- `ROLE_ADMIN`: University club administrators. Authenticates via `/admin/login` using hashed password.
- `ROLE_USER`: Candidates. Authenticates into a stateful, passwordless server session upon registering for an open exam session. Has zero access to `/admin` routes.

## 3. Tech Stack & Environment
- **Framework**: Symfony 7.4 LTS with PHP 8.4
- **ORM & Database**: Doctrine ORM 3.x with PostgreSQL 16
- **Frontend Asset Pipeline**: Symfony AssetMapper + Tailwind CSS v4 + Stimulus UX
- **Audio & Visuals**: HTML5 Canvas 2D particle simulation, Web Audio API `AudioContext` & `AnalyserNode`
- **Administration**: EasyAdmin 5.x
- **Containerization**: Docker Compose, PHP-FPM, Nginx, PostgreSQL 16
- **Timezone**: Consistent UTC storage, user-facing timestamps rendered in `Asia/Tashkent`

## 4. Domain Entities & Attempt State Machine
- `User`: Admin credentials (`ROLE_ADMIN`) and candidate profiles (`ROLE_USER`).
- `Exam`: Settings, acceptance toggle (`is_open`), duration (default 60 mins), results publication status.
- `Section`: Categorical groupings (e.g. Mantiqiy fikrlash, Diskret matematika).
- `Question`: Content (markdown), points, difficulty, image attachment, explanation, options.
- `QuestionOption`: Text and correct status (kept server-side only).
- `ExamAttempt`: Candidate reference, exam reference, states (`IN_PROGRESS`, `SUBMITTED`, `EXPIRED`), `started_at`, `deadline_at`, `submitted_at`, snapshot json, scores.
- `AttemptAnswer`: Selected option, submitted timestamp, revision counter for conflict resolution.
- `AttemptEvent`: Timestamped copy deterrent logs (e.g., visibility change, fullscreen exit).
- `AttemptReset`: Audit log for reset actions by administrators.

## 5. Security Invariants
- Immutable snapshots of questions/options assigned to an attempt upon start.
- Server-authoritative countdowns. Candidate submissions accepted only if `now <= deadline_at`.
- Strictly no leakage of `is_correct` or `explanation` in candidate JSON or DOM.
- Defense against formula injection in spreadsheet exports (cells starting with `=`, `+`, `-`, `@`, `\t`, `\r` escaped).
- Upload validation: strict MIME types (PNG, JPEG, WebP), dimension caps, re-encoding, and server-generated UUID filenames.
