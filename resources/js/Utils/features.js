/**
 * Frontend feature flags
 *
 * Usage:
 * import { RECURRING_TASKS_ENABLED } from '@/Utils/features';
 */

// Recurrence fields are stored on tasks, but nothing generates the repeat
// tasks yet. Keep the UI hidden until the recurring-tasks feature exists.
export const RECURRING_TASKS_ENABLED = false;
