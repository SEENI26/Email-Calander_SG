// GET  /api/counts?date=YYYY-MM-DD&department=QC  -> saved entry + Monday–Saturday week records
// POST /api/counts  { date, department, morning, afternoon }  -> save (insert or update)
import { getSql, ensureSchema } from '../lib/db.js';
import { DEPARTMENTS, isValidDate, weekDates, mondayOf } from '../lib/shared.js';

function parseCount(value) {
  const text = typeof value === 'string' ? value.trim() : value;
  if (text === '' || text === null || text === undefined) return NaN;
  const n = Number(text);
  return Number.isInteger(n) && n >= 0 ? n : NaN;
}

export default async function handler(req, res) {
  try {
    const sql = getSql();
    await ensureSchema(sql);

    if (req.method === 'GET') {
      const date = String(req.query.date || '');
      if (!isValidDate(date)) return res.status(400).json({ error: 'Choose a valid date.' });
      const department = DEPARTMENTS.includes(req.query.department) ? req.query.department : DEPARTMENTS[0];

      const dates = weekDates(date);
      const from = mondayOf(date);
      const to = dates[dates.length - 1];
      const rows = await sql`SELECT to_char(entry_date, 'YYYY-MM-DD') AS entry_date, department, morning_count, afternoon_count
        FROM email_counts WHERE entry_date BETWEEN ${from}::date AND ${to}::date`;

      const records = {};
      for (const row of rows) {
        records[row.entry_date] ??= {};
        records[row.entry_date][row.department] = {
          morning: row.morning_count,
          afternoon: row.afternoon_count,
        };
      }
      const entry = records[date]?.[department] ?? null;

      return res.status(200).json({ date, department, departments: DEPARTMENTS, entry, week: { dates, records } });
    }

    if (req.method === 'POST') {
      const body = req.body || {};
      const date = String(body.date || '');
      const department = String(body.department || '');
      const morning = parseCount(body.morning);
      const afternoon = parseCount(body.afternoon);

      if (!isValidDate(date)) return res.status(400).json({ error: 'Choose a valid date.' });
      if (!DEPARTMENTS.includes(department)) return res.status(400).json({ error: 'Choose one of the listed departments.' });
      if (Number.isNaN(morning) || Number.isNaN(afternoon)) {
        return res.status(400).json({ error: 'Enter whole numbers of zero or more for both shifts.' });
      }

      await sql`INSERT INTO email_counts (entry_date, department, morning_count, afternoon_count, updated_at)
        VALUES (${date}::date, ${department}, ${morning}, ${afternoon}, now())
        ON CONFLICT (entry_date, department) DO UPDATE SET
          morning_count = EXCLUDED.morning_count,
          afternoon_count = EXCLUDED.afternoon_count,
          updated_at = now()`;

      return res.status(200).json({ ok: true });
    }

    res.setHeader('Allow', 'GET, POST');
    return res.status(405).json({ error: 'Method not allowed.' });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ error: 'Something went wrong. Check the database connection.' });
  }
}
