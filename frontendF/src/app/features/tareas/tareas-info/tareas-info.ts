import { Location } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { faFlag, faSliders } from '@fortawesome/pro-regular-svg-icons';
import { ModalController, ToastController } from '@ionic/angular';
import { ModalForm } from '../modal-form/modal-form';
import { ModalFiltros } from '../pages/modal-filtros/modal-filtros';
import { ModalFiltrosAdmin } from '../pages/modal-filtros-admin/modal-filtros-admin';
import { Tarea, TareaAdmin, TareasService } from '../service/tareas.service';

@Component({
  selector: 'app-tareas-info',
  standalone: false,
  templateUrl: './tareas-info.html',
  styleUrl: './tareas-info.scss',
})
export class TareasInfo implements OnInit {
  public tareaAdminSeleccionada: TareaAdmin | null = null;
  public subtareas: Tarea[] = [];
  
  // Variables de estado
  searchTerm: string = '';
  cargandoEmpleados: boolean = true;
  
  // Iconos FontAwesome
  public faSliders = faSliders;
  public faFlag = faFlag;

  // Getter para acceder a apartadoadmin del servicio
  get isApartadoAdmin(): boolean {
    return this.tareasService.apartadoadmin;
  }

  // Función para determinar si una subtarea está activa
  isSubtareaActiva(subtarea: Tarea): boolean {
    return subtarea.estado === 'En progreso' || subtarea.estado === 'Completada';
  }

  constructor(
    private location: Location,
    private tareasService: TareasService,
    private modalController: ModalController,
    private toastController: ToastController,
    private route: ActivatedRoute,
    private router: Router
  ) {}

  ngOnInit() {
    this.obtenerParametrosTarea();
  }

  // Obtener parámetros de la tarea seleccionada
  obtenerParametrosTarea() {
    this.route.queryParams.subscribe(params => {
      if (params['tareaId']) {
        const tareaLocal = this.tareasService.obtenerTareaAdminPorIdLocal(params['tareaId']);
        if (tareaLocal) {
          this.tareaAdminSeleccionada = tareaLocal;
          if (this.tareaAdminSeleccionada && this.tareaAdminSeleccionada.Tarea) {
            this.subtareas = this.tareaAdminSeleccionada.Tarea;
          }
        }
      }
    });
  }

  // Obtener subtareas completadas
  get tareasCompletadas(): Tarea[] {
    return this.subtareas.filter(tarea => tarea.completada);
  }

  // Obtener subtareas pendientes
  get tareasListaPendientes(): Tarea[] {
    return this.subtareas.filter(tarea => !tarea.completada);
  }

  // Obtener subtareas en proceso
  get tareasListaEnProceso(): Tarea[] {
    return this.subtareas.filter(tarea => 
      tarea.estado === 'En progreso' || (tarea.progreso > 0 && !tarea.completada)
    );
  }

  // Obtener información sobre el progreso total
  get progresoGeneral(): { completadas: number; total: number; porcentaje: number } {
    if (!this.tareaAdminSeleccionada) {
      return { completadas: 0, total: 0, porcentaje: 0 };
    }
    return {
      completadas: this.tareaAdminSeleccionada.subtareasCompletadas,
      total: this.tareaAdminSeleccionada.totalSubtareas,
      porcentaje: this.tareaAdminSeleccionada.totalSubtareas > 0 
        ? (this.tareaAdminSeleccionada.subtareasCompletadas / this.tareaAdminSeleccionada.totalSubtareas) * 100 
        : 0
    };
  }

  // Métodos para completar/descompletar subtareas
  completarSubtarea(subtareaId: string): void {
    if (this.tareaAdminSeleccionada) {
      this.tareasService.completarSubtareaAdmin(this.tareaAdminSeleccionada.id, subtareaId);
      if (this.tareaAdminSeleccionada.Tarea) {
        this.subtareas = this.tareaAdminSeleccionada.Tarea;
      }
    }
  }

  descompletarSubtarea(subtareaId: string): void {
    if (this.tareaAdminSeleccionada) {
      this.tareasService.descompletarSubtareaAdmin(this.tareaAdminSeleccionada.id, subtareaId);
      if (this.tareaAdminSeleccionada.Tarea) {
        this.subtareas = this.tareaAdminSeleccionada.Tarea;
      }
    }
  }

  // Método para navegar a subtarea individual
  navegarASubtarea(subtarea: Tarea) {
    this.router.navigate(['/tareas/subtarea-info'], {
      queryParams: {
        tareaId: subtarea.id,
        titulo: subtarea.titulo,
        estado: subtarea.estado,
        categoria: subtarea.Categoria,
        horainicio: subtarea.horainicio,
        horafin: subtarea.horafin,
        descripcion: subtarea.descripcion,
        prioridad: subtarea.Prioridad,
        completada: subtarea.completada,
        progreso: subtarea.progreso,
        fechaAsignacion: subtarea.fechaAsignacion,
        fromTab: 'tareas-admin',
        tareaAdminId: this.tareaAdminSeleccionada?.id
      }
    });
  }

  navegarACrearTarea() {
    this.router.navigate(['/tareas/crear-tarea'], {
      replaceUrl: false,
      skipLocationChange: false
    }).catch(error => {
      console.error('Error al navegar:', error);
      this.mostrarToast('Error al abrir el formulario de crear tarea', 'danger');
    });
  }

  goBack() {
    this.location.back();
  }

  async openModalfiltro() {
    const modal = await this.modalController.create({
      component: ModalFiltros,
      initialBreakpoint: 1,
      breakpoints: [0, 1],
      cssClass: 'modalamedias',
    });

    await modal.present();

    const { data } = await modal.onWillDismiss();
    
    if (data && data.filtrosAplicados) {
      this.aplicarFiltros(data);
      const mensajeFiltro = this.isApartadoAdmin 
        ? '¡Actividad filtrada satisfactoriamente!'
        : '¡Tarea filtrada satisfactoriamente!';
      this.mostrarToast(mensajeFiltro, 'success');
    }
  }

  async openModalfiltroadmin() {
    const modal = await this.modalController.create({
      component: ModalFiltrosAdmin,
      initialBreakpoint: 1,
      breakpoints: [0, 1],
      cssClass: 'modalamedias',
    });

    await modal.present();

    const { data } = await modal.onWillDismiss();
    
    if (data && data.filtrosAplicados) {
      this.aplicarFiltros(data);
      const mensajeFiltro = this.isApartadoAdmin 
        ? '¡Actividad filtrada satisfactoriamente!'
        : '¡Tarea filtrada satisfactoriamente!';
      this.mostrarToast(mensajeFiltro, 'success');
    }
  }

  openmodalfiltronormaloadmin() {
    if (this.tareasService.apartadoadmin === false) {
      this.openModalfiltro();
    } else {
      this.openModalfiltroadmin();
    }
  }

  async abrirModalReaperturar() {
    if (!this.tareaAdminSeleccionada) {
      this.mostrarToast('No hay tarea seleccionada', 'danger');
      return;
    }

    const modal = await this.modalController.create({
      component: ModalForm,
      initialBreakpoint: 1,
      breakpoints: [0, 1],
      cssClass: 'modalamedias',
      componentProps: {
        tarea: this.tareaAdminSeleccionada,
        accion: 'reaperturar'
      }
    });

    await modal.present();

    const { data } = await modal.onWillDismiss();
    
    if (data && data.reaperturada) {
      if (this.tareaAdminSeleccionada) {
        this.tareaAdminSeleccionada.estado = 'Pendiente';
        this.mostrarToast('Tarea reaperturada correctamente', 'success');
      }
    }
  }

  async abrirModalEditar(subtarea: Tarea) {
    this.router.navigate(['/tareas/crear-tarea'], {
      queryParams: {
        edit: 'true',
        tareaId: subtarea.id,
        titulo: subtarea.titulo,
        descripcion: subtarea.descripcion,
        categoria: subtarea.Categoria,
        prioridad: subtarea.Prioridad,
        horainicio: subtarea.horainicio,
        horafin: subtarea.horafin
      }
    });
  }

  private aplicarFiltros(filtros: any) {
    console.log('Aplicando filtros:', filtros);
  }

  // Método para formatear el ID en formato 000
  getFormattedId(id: string | undefined): string {
    if (!id) return '000';
    
    const numeroMatch = id.match(/\d+/);
    if (numeroMatch) {
      const numero = parseInt(numeroMatch[0], 10);
      return numero.toString().padStart(3, '0');
    }
    
    return '000';
  }

  private async mostrarToast(mensaje: string, color: string) {
    const toast = await this.toastController.create({
      message: mensaje,
      duration: 2000,
      color: color,
      position: 'bottom'
    });
    await toast.present();
  }
}
