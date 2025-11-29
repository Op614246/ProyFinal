import { Component, OnInit } from '@angular/core';
import { Location } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { ModalController, ToastController } from '@ionic/angular';
import { ModalForm } from '../modal-form/modal-form';

interface ArchivoAdjunto {
  file?: File;
  url: string;
  nombre: string;
}

@Component({
  selector: 'app-tareaadmin-aperturar',
  standalone: false,
  templateUrl: './tareaadmin-aperturar.html',
  styleUrl: './tareaadmin-aperturar.scss',
})
export class TareaadminAperturar implements OnInit {
  public tareaSeleccionada: any = {};
  public comentario: string = '';
  public archivosAdjuntos: ArchivoAdjunto[] = [];

  constructor(
    private location: Location,
    private route: ActivatedRoute,
    private router: Router,
    private toastController: ToastController,
    private modalController: ModalController
  ) {}

  ngOnInit() {
    this.obtenerParametrosTarea();
  }

  // Obtener parámetros de la tarea seleccionada
  obtenerParametrosTarea() {
    this.route.queryParams.subscribe(params => {
      this.tareaSeleccionada = {
        id: params['tareaId'],
        titulo: params['titulo'],
        estado: params['estado'],
        categoria: params['categoria'],
        sucursal: params['sucursal'],
        responsable: params['responsable'],
        horaprogramada: params['horaprogramada'],
        horainicio: params['horainicio'],
        horafin: params['horafin']
      };
    });
  }

  async openModalReapertura() {
    const modal = await this.modalController.create({
      component: ModalForm,
      initialBreakpoint: 1,
      breakpoints: [0, 1],
      cssClass: 'modal-reapertura',
    });

    await modal.present();
  }

  goBack() {
    this.location.back();
  }

  agregarArchivo() {
    console.log('Agregar archivo');
  }

  eliminarArchivo(index: number) {
    console.log('Eliminar archivo', index);
  }

  async abrirModalReapertura() {
    if (this.tareaSeleccionada?.estado === 'Pendiente') {
      // Navegar a editar tarea
      this.router.navigate(['/features/tareas/crear-tarea'], {
        queryParams: {
          edit: true,
          tareaId: this.tareaSeleccionada.id,
          titulo: this.tareaSeleccionada.titulo,
          estado: this.tareaSeleccionada.estado,
          categoria: this.tareaSeleccionada.categoria,
          responsable: this.tareaSeleccionada.responsable
        }
      });
    } else {
      // Abrir modal de reapertura
      const modal = await this.modalController.create({
        component: ModalForm,
        initialBreakpoint: 0.52,
        breakpoints: [0, 1],
        cssClass: 'modal-reapertura',
        componentProps: {
          tarea: this.tareaSeleccionada,
          accion: 'reaperturar'
        }
      });

      await modal.present();

      const { data, role } = await modal.onWillDismiss();
      
      if (role === 'confirm' && data?.reaperturada) {
        if (data.reasignarTarea) {
          console.log('Reasignando tarea a:', data.cargoSeleccionado, data.usuarioSeleccionado);
        }
        
        this.mostrarToast('Tarea reaperturada exitosamente', 'success');
      }
    }
  }

  get textoBotonAccion(): string {
    return this.tareaSeleccionada?.estado === 'Pendiente' ? 'Editar tarea' : 'Reaperturar tarea';
  }

  get colorBotonAccion(): string {
    return this.tareaSeleccionada?.estado === 'Pendiente' ? 'warning' : 'primary';
  }

  async mostrarToast(mensaje: string, color: string) {
    const toast = await this.toastController.create({
      message: mensaje,
      duration: 2000,
      color: color,
      position: 'bottom'
    });
    toast.present();
  }

  reapturarTarea() {
    console.log('Reapturar tarea', this.tareaSeleccionada);
    console.log('Comentario:', this.comentario);
  }
}
