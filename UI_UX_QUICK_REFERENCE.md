# UI/UX Design Consistency - Quick Reference

## 🎯 Key Changes At A Glance

### Color Standardization
| Component | Color | Gradient |
|-----------|-------|----------|
| Primary Buttons | #1b5e20 | #1b5e20 → #2e7d32 |
| Hover Buttons | #2e7d32 | #2e7d32 → #1b5e20 |
| Yellow Accent | #fbc02d | N/A |
| Error/Reject | #d32f2f | N/A |
| Success/Approve | #16a34a | N/A |

### Button Styles
```css
/* Standard Action Button */
padding: 8px 14px;
border-radius: 8px;
font-weight: 600;
transition: 0.3s ease;

/* Primary Submit Button */
padding: 12px 28px;
background: linear-gradient(135deg, #1b5e20, #2e7d32);
text-transform: uppercase;
letter-spacing: 0.5px;
```

### Form Inputs
```css
border: 2px solid #d1d5db;
border-radius: 8px;
padding: 10px 12px;
font-size: 14px;
transition: all 0.3s ease;

/* On Focus */
border-color: #1b5e20;
box-shadow: 0 0 0 4px rgba(27, 94, 32, 0.15);
```

---

## 📱 Responsive Breakpoints

```css
/* Tablet */
@media (max-width: 1024px) {
  .modal-content { width: 80%; }
  .info-grid { grid-template-columns: 1fr; }
}

/* Large Mobile */
@media (max-width: 768px) {
  .modal-content { width: 95%; }
  .form-select { font-size: 16px; }
}

/* Small Mobile */
@media (max-width: 640px) {
  .modal-content { width: 98%; }
  .submit-btn { width: 100%; }
}
```

---

## ✅ Validation States

### HTML
```html
<label for="field">
  Field Label
  <span class="required-indicator">*</span>
</label>
<input type="text" id="field" required>
<small class="form-help-text">Help text here</small>
<small class="form-error">Error message</small>
```

### CSS Classes
```css
.form-error { color: #d32f2f; }
.form-success { color: #16a34a; }
.form-help-text { color: #6b7280; font-style: italic; }
.required-indicator { color: #d32f2f; }
```

---

## 🎨 Typography

| Element | Size | Weight | Color |
|---------|------|--------|-------|
| Label | 13px | 600 | var(--darker) |
| Help Text | 12px | 400 | #6b7280 |
| Error Text | 12px | 500 | #d32f2f |
| Table Header | 12px | 700 | #fff |
| Status Label | 0.9rem | 600 | #6b7280 |

---

## 🔄 Hover States

```css
/* Buttons */
.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

/* Form Inputs */
.form-select:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 4px rgba(27, 94, 32, 0.15);
}

/* Table Rows */
tr:hover {
  background: linear-gradient(90deg, #f0f9ff 0%, #e0f2fe 100%);
  box-shadow: -3px 0 0 0 var(--primary);
}
```

---

## 📋 Status Boxes

```css
.status-box {
  padding: 24px 20px;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  background: #fff;
}

.status-box:hover {
  transform: translateY(-4px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
}
```

---

## 🔗 Spacing Guide

| Element | Value |
|---------|-------|
| Form Group Gap | 8px |
| Form Group Margin-Bottom | 15px |
| Label Margin-Bottom | 6px |
| Button Padding Vertical | 10-12px |
| Button Padding Horizontal | 14-28px |
| Status Box Padding | 24px 20px |
| Modal Section Padding | 18px |
| Border Radius Small | 6-8px |
| Border Radius Medium | 12px |

---

## ⚡ Transitions

```css
/* Standard Transition */
transition: all 0.3s ease;

/* Quick Feedback */
transition: all 0.2s ease;

/* Smooth Animation */
transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
```

---

## 🎯 Common Components

### Form Group
```html
<div class="form-group">
  <label for="field">
    <i class="fas fa-icon"></i>
    Label Text:
    <span class="required-indicator">*</span>
  </label>
  <input type="text" id="field" class="form-select" required>
  <small class="form-help-text">Help text</small>
</div>
```

### Action Button
```html
<button class="action-btn view-btn">
  <i class="fas fa-eye"></i>
  View Details
</button>
```

### Status Label
```html
<span class="status-label">Status: Pending</span>
```

---

## 🚨 Common Mistakes to Avoid

❌ Don't: Use old button colors (#22c55e, #16a34a)  
✅ Do: Use primary green (#1b5e20) and secondary (#2e7d32)

❌ Don't: Add 1px borders to form inputs  
✅ Do: Use 2px borders for clarity

❌ Don't: Forget `for` attribute on form labels  
✅ Do: Always link labels to inputs

❌ Don't: Skip required field indicators  
✅ Do: Use red asterisks for required fields

❌ Don't: Mix padding values  
✅ Do: Follow the spacing guide

---

## 📞 Questions?

Refer to: `UI_UX_DESIGN_CONSISTENCY_FIXES.md` for detailed documentation.

---

**Last Updated:** November 16, 2025  
**Status:** ✅ Active
