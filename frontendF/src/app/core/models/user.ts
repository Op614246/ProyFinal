/**
 * Interfaz para un usuario del sistema
 */
export interface User {
  id: number;
  username: string;
  role: 'admin' | 'user';
  failedAttempts: number;
  lastAttemptTime: string | null;
  lockoutUntil: string | null;
  isPermanentlyLocked: boolean;
  isActive: boolean;
}

/**
 * Interfaz para crear un nuevo usuario
 */
export interface CreateUserRequest {
  username: string;
  password: string;
  role: 'admin' | 'user';
}