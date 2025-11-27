import { ComponentFixture, TestBed } from '@angular/core/testing';

import { GestionUsuarios } from './gestion-usuarios';

describe('GestionUsuarios', () => {
  let component: GestionUsuarios;
  let fixture: ComponentFixture<GestionUsuarios>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [GestionUsuarios]
    })
    .compileComponents();

    fixture = TestBed.createComponent(GestionUsuarios);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
