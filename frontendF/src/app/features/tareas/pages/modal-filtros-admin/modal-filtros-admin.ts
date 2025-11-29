import { Component, Input, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { faBroomWide, faCheck, faCheckCircle } from '@fortawesome/pro-regular-svg-icons';
import { ModalController } from '@ionic/angular';
import { TareasService } from '../../service/tareas.service';

interface FiltroOpcion {
  nombre: string;
  seleccionado: boolean;
}

@Component({
  selector: 'app-modal-filtros-admin',
  standalone: false,
  templateUrl: './modal-filtros-admin.html',
  styleUrl: './modal-filtros-admin.scss',
})
export class ModalFiltrosAdmin implements OnInit {
  // Iconos FontAwesome
  public faCheck = faCheck;
  public faBroomWide = faBroomWide;
  public faCheckCircle = faCheckCircle;

  // Props recibidas del componente padre
  @Input() selectedTab: string = 'mis-tareas';
  @Input() esAdmin: boolean = true;
  @Input() esTareaInfo: boolean = false;

  // Propiedades para los filtros
  filtroEstado: string = '';
  filtroDepartamento: string = '';
  filtroRol: string = '';
  filtroPrioridad: string = '';
  filtroProgreso: string = '';
  filtroEstadoActivo: string = '';
  filtroCargo: string = '';

  // Opciones disponibles para los selects
  departamentos = ['Todas', 'Sede Centro', 'Sucursal 1', 'Sucursal 2'];
  roles = ['Todos', 'Reportes', 'Supervisión', 'Reuniones', 'Aprobaciones', 'Recursos Humanos'];
  cargos = ['Todos', 'Gerente', 'Supervisor', 'Jefe de Área', 'Coordinador'];
  prioridades = ['Todos', 'Alta', 'Media', 'Baja'];
  progresos = ['Todos', 'Pendiente', 'En progreso', 'Completada'];
  estadosActivos = ['Todos', 'Activo', 'Inactivo'];

  // Filtros para el modo lista
  listadoCategorias: FiltroOpcion[] = [
    { nombre: 'Todos', seleccionado: true },
    { nombre: 'Almacenes', seleccionado: false },
    { nombre: 'Limpieza', seleccionado: false },
    { nombre: 'Operaciones', seleccionado: false },
    { nombre: 'Cocina', seleccionado: false },
    { nombre: 'Servicio', seleccionado: false },
    { nombre: 'Administración', seleccionado: false },
    { nombre: 'Mantenimiento', seleccionado: false }
  ];

  constructor(
    private modalController: ModalController,
    public tareasService: TareasService,
    private router: Router
  ) { }

  ngOnInit() {}

  get esModoLista(): boolean {
    return this.selectedTab === 'tareas-sin-asignar';
  }

  get esModoSelect(): boolean {
    return this.esAdmin || this.selectedTab === 'mis-tareas';
  }

  toggleSeleccion(index: number) {
    if (index === 0) {
      this.listadoCategorias.forEach((item, i) => {
        item.seleccionado = i === 0;
      });
    } else {
      this.listadoCategorias[0].seleccionado = false;
      this.listadoCategorias[index].seleccionado = !this.listadoCategorias[index].seleccionado;
      
      const haySeleccionados = this.listadoCategorias.slice(1).some(item => item.seleccionado);
      if (!haySeleccionados) {
        this.listadoCategorias[0].seleccionado = true;
      }
    }
  }

  cerrarModal() {
    this.modalController.dismiss();
  }

  cancel() {
    this.cerrarModal();
  }

  aplicarFiltros() {
    let filtros: any = {
      filtrosAplicados: true
    };

    if (this.esModoLista) {
      const categoriasSeleccionadas = this.listadoCategorias
        .filter(item => item.seleccionado && item.nombre !== 'Todos')
        .map(item => item.nombre);
      
      filtros.categorias = categoriasSeleccionadas;
      filtros.todasCategorias = this.listadoCategorias[0].seleccionado;
    } else {
      filtros.departamento = this.filtroDepartamento;
      filtros.categoria = this.filtroRol;
      filtros.progreso = this.filtroProgreso;
      filtros.prioridad = this.filtroPrioridad;
      filtros.estadoActivo = this.filtroEstadoActivo;
      filtros.cargo = this.filtroEstado;
    }

    console.log('Filtros a aplicar (admin):', filtros);
    this.modalController.dismiss(filtros, 'filtros');
  }

  limpiarFiltros() {
    if (this.esModoLista) {
      this.listadoCategorias.forEach((item, index) => {
        item.seleccionado = index === 0;
      });
    } else {
      this.filtroEstado = '';
      this.filtroDepartamento = '';
      this.filtroCargo = '';
      this.filtroPrioridad = '';
      this.filtroProgreso = '';
      this.filtroEstadoActivo = '';
      this.filtroRol = '';
    }
  }
}
