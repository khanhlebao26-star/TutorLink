# Local demo data

This seeder is optional and is never called by `DatabaseSeeder` or application startup. It creates two walkthrough accounts with shared credentials (one tutor and one student), plus three supporting tutor records for browsing the Education, Fitness, and Sports categories. The supporting tutors have random undisclosed passwords and are not part of the interaction walkthrough. Listings are mapped to existing specializations so their current category illustrations match the service topic. The seeder also creates weekly availability and deliberately leaves conversations, invitations, Programs, sessions, messages, and reviews empty so the demo operator can perform the workflow live.

Run it only against the local `tutorlink` PostgreSQL database:

```sh
docker compose exec backend php artisan db:seed --class=DatabaseSeeder
docker compose exec backend php artisan db:seed --class=LocalDemoDataSeeder
```

The first command prepares the catalog and configured local admin account. Configure `LOCAL_ADMIN_EMAIL` and `LOCAL_ADMIN_PASSWORD` before using it on a fresh local database. The demo seeder has additional guards for `APP_ENV=local`, the `tutorlink` database name, and a private database address; it also refuses to run if verification documents are required, because it does not generate fake identity evidence.

| Role | Email | Password |
| --- | --- | --- |
| Tutor (Admin-approved local demo profile) | `tutor.demo@tutorlink.test` | `TutorLinkDemo2026!` |
| Student | `student.demo@tutorlink.test` | `TutorLinkDemo2026!` |

The English and math services use the existing education illustration, the strength-training service uses the fitness illustration, and the badminton service uses the sports illustration. No image schema, API, or frontend changes are needed for this seed.
