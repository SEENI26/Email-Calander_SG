# Email Inward Count Tracker (Vercel)

The same tracker as the PHP app, rebuilt for Vercel. The page is `index.html`, the API is `api/counts.js`, and the data is stored in Vercel Postgres. SQLite can't be used on Vercel because its serverless functions don't keep files between requests.

## Deploy

1. Push this `vercel-app` folder to a Git repository, or import it with the Vercel CLI (`npx vercel`).
2. In the Vercel dashboard, open the project, go to **Storage**, and create a **Postgres** database. Connect it to this project. This sets `POSTGRES_URL` automatically.
3. Redeploy. The table `email_counts` is created on the first request.

## Local development

```sh
npm install
npx vercel dev
```

`vercel dev` needs `POSTGRES_URL`. Pull it with `npx vercel env pull .env.local`.

## Timezone

"Today" is calculated in `Asia/Singapore` (see `lib/shared.js` and `index.html`).

## API

- `GET /api/counts?date=YYYY-MM-DD&department=QC` returns the saved entry and the Monday–Saturday week records.
- `POST /api/counts` with JSON `{ "date", "department", "morning", "afternoon" }` saves or updates an entry.
