import { Component, Input, OnInit } from '@angular/core';
import { faCheck, faXmark } from '@fortawesome/pro-regular-svg-icons';
import { ModalController, ToastController } from '@ionic/angular';
import { Tarea, TareaAdmin, TareasService } from '../../service/tareas.service';

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
  usuarioSeleccionado: string = '';

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

  get puedeConfirmar(): boolean {
    if (this.reasignarTarea) {
      return this.motivo.trim().length > 0 && this.cargoSeleccionado.length > 0;
    }
    return this.motivo.trim().length > 0;
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
      motivo: this.motivo,
      reasignarTarea: this.reasignarTarea,
      cargoSeleccionado: this.cargoSeleccionado,
      usuarioSeleccionado: this.usuarioSeleccionado
    };

    this.modalController.dismiss(resultado, 'confirm');
  }

  async abrirModalCargo() {
    // Simular selección de cargo
    const cargos = ['Mesero', 'Supervisor', 'Jefe de Área', 'Coordinador'];
    this.cargoSeleccionado = cargos[Math.floor(Math.random() * cargos.length)];
  }

  async abrirModalUsuario() {
    // Simular selección de usuario
    const usuarios = ['Juan Pérez', 'María García', 'Carlos López', 'Ana Martínez'];
    this.usuarioSeleccionado = usuarios[Math.floor(Math.random() * usuarios.length)];
  }
}
