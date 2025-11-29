import { Component, OnInit } from '@angular/core';
import { Location } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { ModalController, PickerController } from '@ionic/angular';
import { faCaretDown } from '@fortawesome/pro-regular-svg-icons';
import { TareasService } from '../service/tareas.service';

@Component({
  selector: 'app-creartarea',
  standalone: false,
  templateUrl: './creartarea.html',
  styleUrl: './creartarea.scss',
})
export class Creartarea implements OnInit {
  // FontAwesome icons
  public faCaretDown = faCaretDown;

  // Propiedades del formulario
  nombreTarea: string = '';
  descripcionTarea: string = '';
  tipoAsignacion: string = 'cargo';
  cargoSeleccionado: string = 'Seleccionar cargo';
  cargoSeleccionadoObj: any = null;
  usuario: string = 'Seleccionar usuario';
  usuarioSeleccionado: any = null;
  subcategoria: string = '';
  orden: string = '1';
  prioridad: string = 'media';
  estadoActivo: boolean = true;
  
  // Modo edición
  modoEdicion: boolean = false;
  tareaId?: string;

  // Opciones para los selects
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
    { id: '4', nombre: 'Ana Martín', cargo: 'Barista' }
  ];

  constructor(
    private location: Location,
    private route: ActivatedRoute,
    private router: Router,
    private pickerController: PickerController,
    private modalController: ModalController,
    private tareasService: TareasService
  ) {}

  ngOnInit() {
    this.route.queryParams.subscribe(params => {
      if (params['edit'] === 'true') {
        this.modoEdicion = true;
        this.tareaId = params['tareaId'];
        this.nombreTarea = params['titulo'] || '';
        this.descripcionTarea = params['descripcion'] || '';
        this.subcategoria = params['categoria'] || '';
        this.prioridad = params['prioridad']?.toLowerCase() || 'media';
      }
    });
  }
  
  get titulo(): string {
    return this.modoEdicion ? 'Editar tarea' : 'Crear tarea';
  }

  goBack() {
    this.location.back();
  }

  goBackWithSuccess() {
    localStorage.setItem('tareaOperacionExito', JSON.stringify({
      operacion: this.modoEdicion ? 'editada' : 'creada',
      timestamp: Date.now()
    }));
    this.location.back();
  }

  onTipoAsignacionChange() {
    if (this.tipoAsignacion === 'cargo') {
      this.usuario = 'Seleccionar usuario';
      this.usuarioSeleccionado = null;
    }
  }

  async abrirModalCargo() {
    // Implementar modal de selección de cargo
    console.log('Abrir modal cargo');
  }

  async abrirModalUsuario() {
    // Implementar modal de selección de usuario
    console.log('Abrir modal usuario');
  }

  async abrirModalOrden() {
    const opciones = [];
    for (let i = 0; i <= 10; i++) {
      opciones.push({ text: i.toString(), value: i.toString() });
    }

    const picker = await this.pickerController.create({
      columns: [
        {
          name: 'orden',
          options: opciones,
          selectedIndex: parseInt(this.orden) || 1
        }
      ],
      buttons: [
        { text: 'Cancelar', role: 'cancel' },
        {
          text: 'Confirmar',
          handler: (value) => {
            this.orden = value.orden.value;
          }
        }
      ]
    });

    await picker.present();
  }

  async crearTarea() {
    if (!this.nombreTarea.trim()) {
      console.log('Por favor ingrese el nombre de la tarea');
      return;
    }

    if (!this.descripcionTarea.trim()) {
      console.log('Por favor ingrese la descripción de la tarea');
      return;
    }

    const tareaData = {
      ...(this.modoEdicion && this.tareaId && { id: this.tareaId }),
      nombre: this.nombreTarea.trim(),
      descripcion: this.descripcionTarea.trim(),
      tipoAsignacion: this.tipoAsignacion,
      cargo: this.cargoSeleccionado,
      subcategoria: this.subcategoria,
      orden: parseInt(this.orden),
      prioridad: this.prioridad,
      activo: this.estadoActivo,
      ...(this.modoEdicion ? 
        { fechaModificacion: new Date().toISOString() } : 
        { fechaCreacion: new Date().toISOString() }
      )
    };

    console.log(this.modoEdicion ? 'Tarea editada:' : 'Nueva tarea creada:', tareaData);
    
    this.limpiarFormulario();
    this.goBackWithSuccess();
  }

  private limpiarFormulario() {
    this.nombreTarea = '';
    this.descripcionTarea = '';
    this.tipoAsignacion = 'cargo';
    this.cargoSeleccionado = 'Seleccionar cargo';
    this.usuario = 'Seleccionar usuario';
    this.usuarioSeleccionado = null;
    this.subcategoria = '';
    this.orden = '1';
    this.prioridad = 'media';
    this.estadoActivo = true;
    this.modoEdicion = false;
    this.tareaId = undefined;
  }
}
