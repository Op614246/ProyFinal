import { Component, Input, OnInit } from '@angular/core';
import { faCheck, faXmark } from '@fortawesome/pro-regular-svg-icons';
import { ModalController, ToastController } from '@ionic/angular';
import { Tarea, TareaAdmin, TareasService, UserAssignable } from '../../service/tareas.service';

@Component({
  selector: 'app-modal-reaperturar',
  standalone: false,
  templateUrl: './modal-reaperturar.html',
  styleUrl: './modal-reaperturar.scss',
})
export class ModalReaperturar implements OnInit {
  @Input() tarea: any;
  @Input() accion: string = 'reaperturar';

  public faCheck = faCheck;
  public faXmark = faXmark;

  motivo: string = '';
  reasignarTarea: boolean = false;
  cargoSeleccionado: string = '';
  usuarioSeleccionado: string | number = '';

  // Campos para reapertura según diseño
  motivoReapertura: string = '';
  motivosReapertura: string[] = ['Corrección de alcance', 'Falta de información', 'Revisión requerida', 'Error de ejecución'];
  observaciones: string = '';
  prioridadNueva: string = '';
  prioridades: Array<{valor: string, texto: string}> = [
    { valor: 'alta', texto: 'Alta' },
    { valor: 'media', texto: 'Media' },
    { valor: 'baja', texto: 'Baja' }
  ];
  fechaVencimientoNueva: string | null = null;
  // Min date for vencimiento — usar fecha local para evitar desfase UTC
  minFechaVencimiento: string = this.getLocalISOString();

  // Datos reales cargados desde el servicio
  usuarios: UserAssignable[] = [];
  cargos: string[] = [];
  usuariosFiltrados: UserAssignable[] = [];

  /**
   * Obtener fecha/hora actual en formato ISO usando zona horaria local
   * Evita el problema de toISOString() que usa UTC y puede adelantar un día
   */
  private getLocalISOString(date: Date = new Date()): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}`;
  }

  constructor(
    private modalController: ModalController,
    private toastController: ToastController,
    public tareasService: TareasService
  ) { }

  ngOnInit() { }

  onToggleChange() {
    if (!this.reasignarTarea) {
      this.cargoSeleccionado = '';
      this.usuarioSeleccionado = '';
    }
  }

  onCargoChange(event: any) {
    const value = event?.detail?.value ?? event;
    if (value) {
      this.usuariosFiltrados = this.usuarios.filter(u => u.departamento === value);
    } else {
      this.usuariosFiltrados = [...this.usuarios];
    }
  }

  ngAfterViewInit() {
    // Cargar usuarios disponibles para reasignación
    this.tareasService.getAvailableUsers().subscribe({
      next: (response) => {
        if (response?.tipo === 1 && Array.isArray(response.data)) {
          this.usuarios = response.data as UserAssignable[];
          // Extraer cargos únicos
          const cargosSet = new Set<string>();
          this.usuarios.forEach(u => { if (u.departamento) cargosSet.add(u.departamento); });
          this.cargos = Array.from(cargosSet);
          this.usuariosFiltrados = [...this.usuarios];
        }
      },
      error: (err) => {
        console.error('Error cargando usuarios para reasignación:', err);
      }
    });
  }

  get puedeConfirmar(): boolean {
    if (this.reasignarTarea) {
      // Si se reasigna, debe completarse motivo y al menos un objetivo (cargo o usuario)
      const tieneUsuario = this.usuarioSeleccionado !== null && this.usuarioSeleccionado !== '' && this.usuarioSeleccionado !== undefined;
      const motivoVal = (this.motivoReapertura || this.motivo || '').toString();
      return motivoVal.trim().length > 0 && (this.cargoSeleccionado.length > 0 || tieneUsuario);
    }
    const motivoVal = (this.motivoReapertura || this.motivo || '').toString();
    return motivoVal.trim().length > 0;
  }

  onDismiss() {
    this.modalController.dismiss();
  }

  cancelar() {
    this.modalController.dismiss(null, 'cancel');
  }

  async confirmar() {
    if (!this.puedeConfirmar) {
      const toast = await this.toastController.create({
        message: 'Por favor complete los campos requeridos',
        duration: 2000,
        color: 'warning'
      });
      await toast.present();
      return;
    }

    const resultado = {
      reaperturada: true,
      motivo: this.motivoReapertura || this.motivo,
      reasignarTarea: this.reasignarTarea,
      cargoSeleccionado: this.cargoSeleccionado,
      usuarioSeleccionado: this.usuarioSeleccionado,
      observaciones: this.observaciones,
      prioridadNueva: this.prioridadNueva,
      fechaVencimientoNueva: this.fechaVencimientoNueva
    };

    this.modalController.dismiss(resultado, 'confirm');
  }
}
