export const roles = ['admin', 'registration', 'doctor', 'pharmacy', 'laboratory', 'cashier'] as const;
export type Role = typeof roles[number];
export const isRole = (value: unknown): value is Role => typeof value === 'string' && (roles as readonly string[]).includes(value);
export const permits = (role: unknown, allowed: readonly Role[]) => isRole(role) && allowed.includes(role);
export const validRequestId = (value: unknown): value is string => typeof value === 'string' && /^[A-Za-z0-9_-]{1,80}$/.test(value);
export const validBearer = (value: unknown): value is string => typeof value === 'string' && value.length <= 4096 && /^Bearer [A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/i.test(value);
