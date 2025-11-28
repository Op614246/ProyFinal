import { Injectable, PLATFORM_ID, Inject } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { environment } from '../../../environments/environment';

/**
 * CryptoService
 * 
 * Servicio para encriptar/desencriptar datos usando AES-256-CBC
 * Compatible con el backend PHP (CryptoHelper.php)
 * 
 * IMPORTANTE: La clave se deriva usando SHA-256 para compatibilidad con PHP
 * IMPORTANTE: Solo funciona en el navegador (Web Crypto API)
 */
@Injectable({
  providedIn: 'root'
})
export class CryptoService {
  private encryptionKey: string = environment.encryptionKey;
  private derivedKey: CryptoKey | null = null;
  private isBrowser: boolean;

  constructor(@Inject(PLATFORM_ID) platformId: Object) {
    this.isBrowser = isPlatformBrowser(platformId);
  }

  /**
   * Deriva la clave usando SHA-256 (compatible con PHP hash('sha256', $key, true))
   */
  private async getDerivedKey(usage: 'encrypt' | 'decrypt'): Promise<CryptoKey> {
    if (!this.isBrowser) {
      throw new Error('Crypto API no disponible en servidor');
    }
    
    // Convertir la clave string a bytes
    const encoder = new TextEncoder();
    const keyData = encoder.encode(this.encryptionKey);
    
    // Generar hash SHA-256 de la clave (igual que PHP)
    const hashBuffer = await crypto.subtle.digest('SHA-256', keyData);
    
    // Importar como clave AES-CBC
    return await crypto.subtle.importKey(
      'raw',
      hashBuffer,
      { name: 'AES-CBC' },
      false,
      [usage]
    );
  }

  /**
   * Encripta un objeto JSON para enviar al backend
   * @param data Objeto a encriptar
   * @returns { payload: string, iv: string } en Base64
   */
  async encrypt(data: any): Promise<{ payload: string; iv: string }> {
    if (!this.isBrowser) {
      throw new Error('Encriptación solo disponible en navegador');
    }
    
    const plaintext = JSON.stringify(data);
    
    // Generar IV aleatorio de 16 bytes
    const iv = crypto.getRandomValues(new Uint8Array(16));
    
    // Obtener clave derivada con SHA-256
    const cryptoKey = await this.getDerivedKey('encrypt');
    
    // Encriptar
    const plaintextBuffer = this.stringToArrayBuffer(plaintext);
    const encryptedBuffer = await crypto.subtle.encrypt(
      { name: 'AES-CBC', iv: iv },
      cryptoKey,
      plaintextBuffer
    );
    
    // Convertir a Base64
    const payload = this.arrayBufferToBase64(encryptedBuffer);
    const ivBase64 = this.arrayBufferToBase64(iv.buffer);
    
    return { payload, iv: ivBase64 };
  }

  /**
   * Desencripta datos recibidos del backend
   * @param payload Datos encriptados en Base64
   * @param iv Vector de inicialización en Base64
   * @returns Objeto desencriptado
   */
  async decrypt(payload: string, iv: string): Promise<any> {
    if (!this.isBrowser) {
      throw new Error('Desencriptación solo disponible en navegador');
    }
    
    try {
      // Convertir de Base64 a ArrayBuffer
      const encryptedBuffer = this.base64ToArrayBuffer(payload);
      const ivBuffer = this.base64ToArrayBuffer(iv);
      
      // Obtener clave derivada con SHA-256
      const cryptoKey = await this.getDerivedKey('decrypt');
      
      // Desencriptar
      const decryptedBuffer = await crypto.subtle.decrypt(
        { name: 'AES-CBC', iv: ivBuffer },
        cryptoKey,
        encryptedBuffer
      );
      
      // Convertir a string y parsear JSON
      const decryptedText = this.arrayBufferToString(decryptedBuffer);
      return JSON.parse(decryptedText);
    } catch (error) {
      console.error('Error al desencriptar:', error);
      throw new Error('Error al desencriptar los datos');
    }
  }

  /**
   * Convierte string a ArrayBuffer (UTF-8)
   */
  private stringToArrayBuffer(str: string): ArrayBuffer {
    const encoder = new TextEncoder();
    return encoder.encode(str).buffer;
  }

  /**
   * Convierte ArrayBuffer a string (UTF-8)
   */
  private arrayBufferToString(buffer: ArrayBuffer): string {
    const decoder = new TextDecoder();
    return decoder.decode(buffer);
  }

  /**
   * Convierte ArrayBuffer a Base64
   */
  private arrayBufferToBase64(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    bytes.forEach(byte => binary += String.fromCharCode(byte));
    return btoa(binary);
  }

  /**
   * Convierte Base64 a ArrayBuffer
   */
  private base64ToArrayBuffer(base64: string): ArrayBuffer {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
  }
}
