import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ModalReaperturar } from './modal-reaperturar';

describe('ModalReaperturar', () => {
  let component: ModalReaperturar;
  let fixture: ComponentFixture<ModalReaperturar>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [ModalReaperturar]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ModalReaperturar);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
