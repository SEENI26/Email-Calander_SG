// Vercel Postgres connection. Vercel sets POSTGRES_URL when a Postgres
// database is linked to the project (Storage tab in the Vercel dashboard).
import { neon } from '@neondatabase/serverless';

let schemaReady = null;

export function getSql() {
  if (!process.env.POSTGRES_URL) {
    throw new Error('POSTGRES_URL is not set. Link a Postgres database to this Vercel project.');
  }
  return neon(process.env.POSTGRES_URL);
}

// Creates the table once per server instance. Safe to run repeatedly.
export function ensureSchema(sql) {
  if (!schemaReady) {
    schemaReady = sql`CREATE TABLE IF NOT EXISTS email_counts (
      entry_date DATE NOT NULL,
      department TEXT NOT NULL,
      morning_count INTEGER NOT NULL DEFAULT 0 CHECK (morning_count >= 0),
      afternoon_count INTEGER NOT NULL DEFAULT 0 CHECK (afternoon_count >= 0),
      updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
      PRIMARY KEY (entry_date, department)
    )`.catch((err) => {
      schemaReady = null; // allow a retry on the next request
      throw err;
    });
  }
  return schemaReady;
}
