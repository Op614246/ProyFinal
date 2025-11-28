/**
 * Interfaz para la respuesta del API
 */
export interface ApiResponse<T = any> {
  tipo: number;       // 1 = éxito, 2 = advertencia, 3 = error
  mensajes: string[];
  data: T | null;
}

/**
 * Interfaz para la respuesta encriptada
 */
export interface EncryptedResponse {
  encrypted: boolean;
  payload: string;
  iv: string;
}

/**
 * Interfaz para el usuario autenticado
 */
export interface AuthUser {
  id?: number;
  username: string;
  role: string;
  token?: string;
}