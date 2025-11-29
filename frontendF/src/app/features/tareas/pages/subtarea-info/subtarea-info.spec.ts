import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SubtareaInfo } from './subtarea-info';

describe('SubtareaInfo', () => {
  let component: SubtareaInfo;
  let fixture: ComponentFixture<SubtareaInfo>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [SubtareaInfo]
    })
    .compileComponents();

    fixture = TestBed.createComponent(SubtareaInfo);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
