// Shared constants and date helpers used by the API routes.
export const DEPARTMENTS = ['Design Center', 'QC', 'Account'];
export const TIMEZONE = 'Asia/Singapore';

export function isValidDate(value) {
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
  const d = new Date(value + 'T00:00:00Z');
  return !Number.isNaN(d.getTime()) && d.toISOString().slice(0, 10) === value;
}

// Monday of the week containing `value`, as YYYY-MM-DD.
export function mondayOf(value) {
  const d = new Date(value + 'T00:00:00Z');
  const offset = (d.getUTCDay() + 6) % 7; // Monday = 0 ... Sunday = 6
  d.setUTCDate(d.getUTCDate() - offset);
  return d.toISOString().slice(0, 10);
}

// The six report dates Monday–Saturday for the week containing `value`.
export function weekDates(value) {
  const monday = mondayOf(value);
  return Array.from({ length: 6 }, (_, i) => {
    const d = new Date(monday + 'T00:00:00Z');
    d.setUTCDate(d.getUTCDate() + i);
    return d.toISOString().slice(0, 10);
  });
}

// Today's date in Singapore time, as YYYY-MM-DD.
export function todayInSingapore() {
  return new Intl.DateTimeFormat('en-CA', { timeZone: TIMEZONE, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
}
