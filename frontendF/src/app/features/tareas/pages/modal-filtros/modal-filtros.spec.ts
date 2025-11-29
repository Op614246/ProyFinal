import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ModalFiltros } from './modal-filtros';

describe('ModalFiltros', () => {
  let component: ModalFiltros;
  let fixture: ComponentFixture<ModalFiltros>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ModalFiltros]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ModalFiltros);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
