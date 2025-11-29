import { ComponentFixture, TestBed } from '@angular/core/testing';

import { TareaadminAperturar } from './tareaadmin-aperturar';

describe('TareaadminAperturar', () => {
  let component: TareaadminAperturar;
  let fixture: ComponentFixture<TareaadminAperturar>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [TareaadminAperturar]
    })
    .compileComponents();

    fixture = TestBed.createComponent(TareaadminAperturar);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
