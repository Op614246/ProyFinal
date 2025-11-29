import { ComponentFixture, TestBed } from '@angular/core/testing';

import { TareasInfo } from './tareas-info';

describe('TareasInfo', () => {
  let component: TareasInfo;
  let fixture: ComponentFixture<TareasInfo>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [TareasInfo]
    })
    .compileComponents();

    fixture = TestBed.createComponent(TareasInfo);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
