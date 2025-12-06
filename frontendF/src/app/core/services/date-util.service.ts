import { Injectable } from '@angular/core';

@Injectable({
  providedIn: 'root'
})
export class DateUtilService {
  
  /**
   * Obtiene la fecha actual en UTC-5 (America/Lima timezone)
   * @returns {Date} Fecha actual en UTC-5
   */
  getNowUTC5(): Date {
    // Obtener la hora actual en UTC
    const now = new Date();
    
    // Convertir a UTC-5 (America/Lima)
    // UTC-5 significa 5 horas atrás de UTC
    const formatter = new Intl.DateTimeFormat('es-PE', {
      timeZone: 'America/Lima',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false
    });
    
    const parts = formatter.formatToParts(now);
    const date = new Date();
    
    // Extraer componentes
    let year = now.getFullYear();
    let month = 0;
    let day = 0;
    let hour = 0;
    let minute = 0;
    let second = 0;
    
    for (const part of parts) {
      switch (part.type) {
        case 'year':
          year = parseInt(part.value);
          break;
        case 'month':
          month = parseInt(part.value) - 1; // Month es 0-indexed
          break;
        case 'day':
          day = parseInt(part.value);
          break;
        case 'hour':
          hour = parseInt(part.value);
          break;
        case 'minute':
          minute = parseInt(part.value);
          break;
        case 'second':
          second = parseInt(part.value);
          break;
      }
    }
    
    return new Date(year, month, day, hour, minute, second);
  }
  
  /**
   * Obtiene la fecha actual en UTC-5 como string YYYY-MM-DD (formato para inputs date)
   * @returns {string} Fecha en formato YYYY-MM-DD
   */
  getTodayString(): string {
    const now = this.getNowUTC5();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }
  
  /**
   * Obtiene la fecha actual como ISO string (YYYY-MM-DDTHH:mm:ss)
   * @returns {string} Fecha ISO string
   */
  getNowISOString(): string {
    const now = this.getNowUTC5();
    return now.toISOString().split('Z')[0]; // Remover el Z de UTC
  }

  /**
   * Compara dos fechas
   * @param fecha1 Primera fecha
   * @param fecha2 Segunda fecha
   * @returns Negativo si fecha1 < fecha2, 0 si son iguales, positivo si fecha1 > fecha2
   */
  compareFechas(fecha1: string, fecha2: string): number {
    const d1 = new Date(fecha1 + 'T00:00:00');
    const d2 = new Date(fecha2 + 'T00:00:00');
    return d1.getTime() - d2.getTime();
  }

  /**
   * Obtiene el número de día de la semana (0 = domingo, 6 = sábado)
   * @returns {number} Día de la semana
   */
  getDayOfWeekUTC5(): number {
    return this.getNowUTC5().getDay();
  }

  /**
   * Obtiene el número de semana del año
   * @returns {number} Número de semana
   */
  getWeekNumberUTC5(): number {
    const now = this.getNowUTC5();
    const firstDay = new Date(now.getFullYear(), 0, 1);
    const diff = now.getTime() - firstDay.getTime();
    const oneWeek = 7 * 24 * 60 * 60 * 1000;
    return Math.ceil(diff / oneWeek);
  }

  /**
   * Formatea una fecha a string legible
   * @param date Fecha a formatear
   * @param format Formato deseado (default: 'DD/MM/YYYY')
   * @returns {string} Fecha formateada
   */
  formatDate(date: Date | string, format: string = 'DD/MM/YYYY'): string {
    const d = typeof date === 'string' ? new Date(date) : date;
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hour = String(d.getHours()).padStart(2, '0');
    const minute = String(d.getMinutes()).padStart(2, '0');
    const second = String(d.getSeconds()).padStart(2, '0');

    return format
      .replace('YYYY', year.toString())
      .replace('MM', month)
      .replace('DD', day)
      .replace('HH', hour)
      .replace('mm', minute)
      .replace('ss', second);
  }
}
