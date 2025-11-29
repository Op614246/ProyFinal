import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ModalFiltrosAdmin } from './modal-filtros-admin';

describe('ModalFiltrosAdmin', () => {
  let component: ModalFiltrosAdmin;
  let fixture: ComponentFixture<ModalFiltrosAdmin>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ModalFiltrosAdmin]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ModalFiltrosAdmin);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
