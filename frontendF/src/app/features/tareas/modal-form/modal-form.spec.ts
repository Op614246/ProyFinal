import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ModalForm } from './modal-form';

describe('ModalForm', () => {
  let component: ModalForm;
  let fixture: ComponentFixture<ModalForm>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ModalForm]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ModalForm);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
