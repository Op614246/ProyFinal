import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Creartarea } from './creartarea';

describe('Creartarea', () => {
  let component: Creartarea;
  let fixture: ComponentFixture<Creartarea>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [Creartarea]
    })
    .compileComponents();

    fixture = TestBed.createComponent(Creartarea);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
