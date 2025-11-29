import { Component, Input, OnInit } from '@angular/core';
import { ModalController } from '@ionic/angular';
import { faCircleChevronRight, faCaretDown } from '@fortawesome/pro-regular-svg-icons';

@Component({
  selector: 'app-modal-form',
  standalone: false,
  templateUrl: './modal-form.html',
  styleUrl: './modal-form.scss',
})
export class ModalForm implements OnInit {
  // Icons
  public faCircleChevronRight = faCircleChevronRight;
  public faCaretDown = faCaretDown;
  
  @Input() tarea: any;
  @Input() accion: string = '';

  // Propiedades del formulario
  public motivo: string = '';
  public reasignarTarea: boolean = false;
  public cargoSeleccionado: string = '';
  public usuarioSeleccionado: string = '';

  // Datos disponibles
  cargos = [
    { id: 'cajero', nombre: 'Cajero', descripcion: 'Encargado de caja' },
    { id: 'chef', nombre: 'Chef', descripcion: 'Responsable de cocina' },
    { id: 'mesero', nombre: 'Mesero', descripcion: 'Atención al cliente' },
    { id: 'barista', nombre: 'Barista', descripcion: 'Preparación de bebidas' },
    { id: 'limpieza', nombre: 'Limpieza', descripcion: 'Mantenimiento' },
    { id: 'supervisor', nombre: 'Supervisor', descripcion: 'Supervisión' }
  ];

  usuarios = [
    { id: '1', nombre: 'Juan Pérez', cargo: 'Cajero' },
    { id: '2', nombre: 'María García', cargo: 'Chef' },
    { id: '3', nombre: 'Carlos López', cargo: 'Mesero' },
    { id: '4', nombre: 'Ana Martín', cargo: 'Barista' },
    { id: '5', nombre: 'Luis Rodríguez', cargo: 'Limpieza' },
    { id: '6', nombre: 'Carmen Sánchez', cargo: 'Supervisor' }
  ];

  constructor(private modalController: ModalController) {}

  ngOnInit() {}

  // Toggle reasignar
  onToggleChange() {
    if (!this.reasignarTarea) {
      this.cargoSeleccionado = '';
      this.usuarioSeleccionado = '';
    }
  }

  // Cerrar modal
  onDismiss() {
    this.modalController.dismiss();
  }

  cancelar() {
    this.modalController.dismiss();
  }

  // Confirmar y enviar datos
  confirmar() {
    const data = {
      reaperturaConfirmada: true,
      motivo: this.motivo,
      reasignarTarea: this.reasignarTarea,
      cargoSeleccionado: this.cargoSeleccionado,
      usuarioSeleccionado: this.usuarioSeleccionado,
      reaperturada: true
    };
    
    this.modalController.dismiss(data, 'confirm');
  }

  // Validación
  get puedeConfirmar(): boolean {
    return !!(this.motivo && this.motivo.trim() !== '');
  }

  // Modal selección de cargo
  async abrirModalCargo() {
    // Implementar o usar picker
    console.log('Abrir modal cargo');
  }

  // Modal selección de usuario
  async abrirModalUsuario() {
    // Implementar o usar picker
    console.log('Abrir modal usuario');
  }
}
