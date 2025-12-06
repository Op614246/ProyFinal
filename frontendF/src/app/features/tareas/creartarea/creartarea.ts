import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { Location } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { AlertController, ModalController, PickerController } from '@ionic/angular';
import { faCaretDown, faXmark } from '@fortawesome/pro-regular-svg-icons';
import { TareasService, Categoria, UserAssignable } from '../service/tareas.service';
import { ToastService } from '../../../core/services/toast.service';
import { DateUtilService } from '../../../core/services/date-util.service';

@Component({
  selector: 'app-creartarea',
  standalone: false,
  templateUrl: './creartarea.html',
  styleUrl: './creartarea.scss',
})
export class Creartarea implements OnInit {
  // FontAwesome icons
  public faCaretDown = faCaretDown;
  public faXMark = faXmark;

  // Propiedades del formulario
  nombreTarea: string = '';
  descripcionTarea: string = '';
  
  // Toggle de asignación
  asignarUsuario: boolean = false;
  tipoAsignacion: string = 'usuario';
  
  // Selección de cargo/usuario
  cargoSeleccionado: string = 'Seleccionar cargo';
  cargoSeleccionadoObj: any = null;
  usuario: string = 'Seleccionar usuario';
  usuarioSeleccionado: UserAssignable | null = null;
  
  // Otras propiedades
  categoriaSeleccionada: string = '';
  categoriaSeleccionadaId: number | null = null;
  sucursalSeleccionada: string = '';
  sucursalSeleccionadaId: number | null = null;
  orden: string = '1';
  prioridad: string = 'medium';
  estadoActivo: boolean = true;
  
  // Fechas
  fechaAsignacion: string = new Date().toISOString().split('T')[0];
  fechaLimite: string = '';
  horaInicio: string = '';
  horaFin: string = '';
  minFechaAsignacion: string = new Date().toISOString().split('T')[0]; // Mínimo = hoy
  minHoraInicio: string = ''; // Se calcula dinámicamente
  
  // Modo edición
  modoEdicion: boolean = false;
  tareaId?: string;
  
  // Loading states
  cargandoUsuarios: boolean = false;
  cargandoCategorias: boolean = false;
  cargandoSucursales: boolean = false;

  // Datos del backend
  categorias: Categoria[] = [];
  usuarios: UserAssignable[] = [];
  sucursales: any[] = [];
  cargos: string[] = [];

  constructor(
    private location: Location,
    private route: ActivatedRoute,
    private router: Router,
    private pickerController: PickerController,
    private modalController: ModalController,
    private alertController: AlertController,
    private tareasService: TareasService,
    private toastService: ToastService,
    private dateUtilService: DateUtilService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit() {
    // Cargar datos del backend
    this.cargarDatos();
    
    // Usar fecha actual en UTC-5 en lugar de new Date()
    this.fechaAsignacion = this.dateUtilService.getTodayString();
    this.minFechaAsignacion = this.dateUtilService.getTodayString();
    
    // Watcher para detectar cambios en fechaAsignacion
    this.onFechaAsignacionChange();
    
    this.route.queryParams.subscribe(params => {
      if (params['edit'] === 'true') {
        this.modoEdicion = true;
        this.tareaId = params['tareaId'];
        this.nombreTarea = params['titulo'] || '';
        this.descripcionTarea = params['descripcion'] || '';
        this.categoriaSeleccionada = params['categoria'] || '';
        this.prioridad = params['prioridad']?.toLowerCase() || 'medium';
        
        // Si hay usuario asignado, activar el toggle
        if (params['usuarioAsignado']) {
          this.asignarUsuario = true;
        }
      }
    });
  }
  
  cargarDatos() {
    // Cargar usuarios disponibles
    this.cargandoUsuarios = true;
    this.tareasService.getAvailableUsers().subscribe({
      next: (response) => {
        if (response.tipo === 1) {
          this.usuarios = response.data;
          // Extraer cargos únicos de los usuarios
          const cargosSet = new Set<string>();
          this.usuarios.forEach(u => {
            if (u.departamento) {
              cargosSet.add(u.departamento);
            }
          });
          this.cargos = Array.from(cargosSet);
        }
        this.cargandoUsuarios = false;
      },
      error: (err) => {
        console.error('Error cargando usuarios:', err);
        this.cargandoUsuarios = false;
      }
    });
    
    // Cargar categorías
    this.cargandoCategorias = true;
    this.tareasService.getCategorias().subscribe({
      next: (response) => {
        if (response.tipo === 1) {
          this.categorias = response.data;
        }
        this.cargandoCategorias = false;
      },
      error: (err) => {
        console.error('Error cargando categorías:', err);
        this.cargandoCategorias = false;
      }
    });
    
    // Cargar sucursales
    this.cargandoSucursales = true;
    this.tareasService.getSucursales().subscribe({
      next: (response) => {
        if (response.tipo === 1) {
          this.sucursales = response.data;
        }
        this.cargandoSucursales = false;
      },
      error: (err) => {
        console.error('Error cargando sucursales:', err);
        this.cargandoSucursales = false;
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
    // Limpiar selecciones cuando cambia el tipo
    if (this.tipoAsignacion === 'cargo') {
      this.usuario = 'Seleccionar usuario';
      this.usuarioSeleccionado = null;
    } else {
      this.cargoSeleccionado = 'Seleccionar cargo';
      this.cargoSeleccionadoObj = null;
    }
  }
  
  onAsignarUsuarioChange() {
    // Limpiar selecciones cuando se desactiva la asignación
    if (!this.asignarUsuario) {
      this.usuario = 'Seleccionar usuario';
      this.usuarioSeleccionado = null;
      this.cargoSeleccionado = 'Seleccionar cargo';
      this.cargoSeleccionadoObj = null;
    }
  }

  async abrirModalCargo() {
    if (this.cargos.length === 0) {
      this.toastService.warning('No hay cargos disponibles');
      return;
    }
    
    const inputs = this.cargos.map(cargo => ({
      type: 'radio' as const,
      label: cargo,
      value: cargo,
      checked: this.cargoSeleccionado === cargo
    }));
    
    const alert = await this.alertController.create({
      header: 'Seleccionar cargo',
      inputs,
      buttons: [
        { text: 'Cancelar', role: 'cancel' },
        {
          text: 'Confirmar',
          handler: (data) => {
            if (data) {
              this.cargoSeleccionado = data;
              this.cargoSeleccionadoObj = { nombre: data };
              // Filtrar usuarios por cargo seleccionado
              this.usuario = 'Seleccionar usuario';
              this.usuarioSeleccionado = null;
            }
          }
        }
      ]
    });
    
    await alert.present();
  }

  async abrirModalUsuario() {
    if (this.usuarios.length === 0) {
      this.toastService.warning('No hay usuarios disponibles');
      return;
    }
    
    // Filtrar usuarios por cargo si está seleccionado
    let usuariosFiltrados = this.usuarios;
    if (this.tipoAsignacion === 'cargo' && this.cargoSeleccionadoObj) {
      usuariosFiltrados = this.usuarios.filter(u => u.departamento === this.cargoSeleccionado);
    }
    
    if (usuariosFiltrados.length === 0) {
      this.toastService.warning('No hay usuarios disponibles para el cargo seleccionado');
      return;
    }
    
    const inputs = usuariosFiltrados.map(usuario => ({
      type: 'radio' as const,
      label: `${usuario.nombre_completo} ${usuario.departamento ? '(' + usuario.departamento + ')' : ''}`,
      value: usuario.id.toString(),
      checked: this.usuarioSeleccionado?.id === usuario.id
    }));
    
    const alert = await this.alertController.create({
      header: 'Seleccionar usuario',
      inputs,
      buttons: [
        { text: 'Cancelar', role: 'cancel' },
        {
          text: 'Confirmar',
          handler: (data) => {
            if (data) {
              const usuarioSel = this.usuarios.find(u => u.id.toString() === data);
              if (usuarioSel) {
                this.usuarioSeleccionado = usuarioSel;
                this.usuario = usuarioSel.nombre_completo;
              }
            }
          }
        }
      ]
    });
    
    await alert.present();
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
      this.toastService.warning('Por favor ingrese el nombre de la tarea');
      return;
    }

    if (!this.descripcionTarea.trim()) {
      this.toastService.warning('Por favor ingrese la descripción de la tarea');
      return;
    }
    
    if (this.asignarUsuario && !this.usuarioSeleccionado) {
      this.toastService.warning('Por favor seleccione un usuario para asignar la tarea');
      return;
    }

    // VALIDACIÓN: deadline no puede ser antes que fecha_asignacion
    if (this.fechaLimite && this.fechaAsignacion) {
      const fechaAsignacionObj = new Date(this.fechaAsignacion + 'T00:00:00');
      const fechaLimiteObj = new Date(this.fechaLimite + 'T23:59:59');
      
      if (fechaLimiteObj < fechaAsignacionObj) {
        this.toastService.error('La fecha límite no puede ser anterior a la fecha de asignación');
        return;
      }
    }

    // VALIDACIÓN: horaInicio no puede ser anterior a la hora actual (si es hoy)
    if (this.horaInicio) {
      const hoy = this.dateUtilService.getTodayString();
      const ahora = this.dateUtilService.getNowUTC5();
      const horaActual = ahora.getHours().toString().padStart(2, '0') + ':' + ahora.getMinutes().toString().padStart(2, '0');
      
      if (this.fechaAsignacion === hoy) {
        const horaInicioObj = new Date('2000-01-01T' + this.horaInicio);
        const horaActualObj = new Date('2000-01-01T' + horaActual);
        
        if (horaInicioObj <= horaActualObj) {
          this.toastService.error('La hora de inicio debe ser posterior a la hora actual');
          this.horaInicio = ''; // Limpiar la hora inválida
          return;
        }
      }
    }

    // VALIDACIÓN: Si es el mismo día, horaFin >= horaInicio
    if (this.fechaAsignacion === this.fechaLimite && this.horaInicio && this.horaFin) {
      const horaInicioObj = new Date('2000-01-01T' + this.horaInicio);
      const horaFinObj = new Date('2000-01-01T' + this.horaFin);
      
      if (horaFinObj < horaInicioObj) {
        this.toastService.error('La hora de fin no puede ser anterior a la hora de inicio');
        return;
      }
    }

    const tareaData: any = {
      ...(this.modoEdicion && this.tareaId && { id: parseInt(this.tareaId) }),
      title: this.nombreTarea.trim(),
      description: this.descripcionTarea.trim(),
      priority: this.prioridad,
      status: this.estadoActivo ? 'pending' : 'inactive',
      categoria_id: this.categoriaSeleccionadaId,
      sucursal_id: this.sucursalSeleccionadaId,
      orden: parseInt(this.orden),
      fecha_asignacion: this.fechaAsignacion,
      deadline: this.fechaLimite || null,
      horainicio: this.horaInicio || null,
      horafin: this.horaFin || null,
    };
    
    // Si se asigna a usuario, agregar el ID
    if (this.asignarUsuario && this.usuarioSeleccionado) {
      tareaData.assigned_user_id = this.usuarioSeleccionado.id;
    }

    console.log(this.modoEdicion ? 'Tarea editada:' : 'Nueva tarea creada:', tareaData);
    
    // Llamar al servicio para crear/editar la tarea
    this.tareasService.createTask(tareaData).subscribe({
      next: (response) => {
        if (response.tipo === 1) {
          this.toastService.success(this.modoEdicion ? 'Tarea actualizada correctamente' : 'Tarea creada correctamente');
          this.limpiarFormulario();
          this.goBackWithSuccess();
        } else {
          this.toastService.error(response.mensajes[0] || 'Error al guardar la tarea');
        }
      },
      error: (err) => {
        console.error('Error al guardar tarea:', err);
        this.toastService.error('Error al guardar la tarea');
      }
    });
  }

  private limpiarFormulario() {
    this.nombreTarea = '';
    this.descripcionTarea = '';
    this.asignarUsuario = false;
    this.tipoAsignacion = 'usuario';
    this.cargoSeleccionado = 'Seleccionar cargo';
    this.cargoSeleccionadoObj = null;
    this.usuario = 'Seleccionar usuario';
    this.usuarioSeleccionado = null;
    this.categoriaSeleccionada = '';
    this.categoriaSeleccionadaId = null;
    this.sucursalSeleccionada = '';
    this.sucursalSeleccionadaId = null;
    this.orden = '1';
    this.prioridad = 'medium';
    this.estadoActivo = true;
    this.fechaAsignacion = this.dateUtilService.getTodayString();
    this.fechaLimite = '';
    this.horaInicio = '';
    this.horaFin = '';
    this.modoEdicion = false;
    this.tareaId = undefined;
  }
  
  onCategoriaChange(event: any) {
    const categoriaId = event.detail.value;
    const categoria = this.categorias.find(c => c.id === categoriaId);
    if (categoria) {
      this.categoriaSeleccionadaId = categoria.id;
      this.categoriaSeleccionada = categoria.nombre;
    }
  }
  
  onSucursalChange(event: any) {
    const sucursalId = event.detail.value;
    const sucursal = this.sucursales.find(s => s.id === sucursalId);
    if (sucursal) {
      this.sucursalSeleccionadaId = sucursal.id;
      this.sucursalSeleccionada = sucursal.nombre;
    }
  }

  // Getter para calcular la hora mínima permitida (hora actual + 1 minuto si es hoy)
  getMinHoraInicio(): string {
    const hoy = this.dateUtilService.getTodayString();
    
    // Si la fecha de asignación es hoy, retornar la hora actual + 1 minuto
    if (this.fechaAsignacion === hoy) {
      const ahora = this.dateUtilService.getNowUTC5();
      // Sumar 1 minuto para que no se pueda seleccionar la hora actual exacta
      ahora.setMinutes(ahora.getMinutes() + 1);
      const horas = ahora.getHours().toString().padStart(2, '0');
      const minutos = ahora.getMinutes().toString().padStart(2, '0');
      return `${horas}:${minutos}`;
    }
    
    // Si es una fecha futura, no hay restricción de hora
    return '00:00';
  }

  // Detección automática de cambios en fechaAsignacion
  onFechaAsignacionChange() {
    // Si la fechaAsignacion cambia a una fecha posterior a fechaLimite, limpiar fechaLimite
    if (this.fechaAsignacion && this.fechaLimite) {
      const fechaAsignacion = new Date(this.fechaAsignacion);
      const fechaLimite = new Date(this.fechaLimite);
      
      if (fechaAsignacion > fechaLimite) {
        // Limpiar fecha límite para que el usuario seleccione una nueva
        this.fechaLimite = '';
        this.horaFin = ''; // También limpiar hora fin para mantener consistencia
        this.cdr.detectChanges(); // Detectar cambios automáticamente
      }
    }
  }

  // Validación de hora de inicio en tiempo real
  onHoraInicioChange() {
    if (this.horaInicio) {
      const hoy = this.dateUtilService.getTodayString();
      const ahora = this.dateUtilService.getNowUTC5();
      const horaActual = ahora.getHours().toString().padStart(2, '0') + ':' + ahora.getMinutes().toString().padStart(2, '0');
      
      if (this.fechaAsignacion === hoy) {
        const horaInicioObj = new Date('2000-01-01T' + this.horaInicio);
        const horaActualObj = new Date('2000-01-01T' + horaActual);
        
        // Si la hora es inválida (menor o igual a la actual), limpiar
        if (horaInicioObj <= horaActualObj) {
          this.horaInicio = '';
          this.toastService.warning('La hora de inicio debe ser posterior a la hora actual');
        }
      }
    }
  }
}
