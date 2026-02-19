# 🎨 ATRIOS ATS - DESIGN SYSTEM

## Overview

A professional, maintainable design system for the Atrios Recruitment ATS. Built with clarity, consistency, and scalability in mind.

---

## 📁 File Structure

```
/assets/
├── css/
│   ├── variables.css      → Design tokens (colors, fonts, spacing)
│   ├── layout.css         → Layout structure (sidebar, navbar, grid)
│   ├── components.css     → Reusable UI components
│   └── style.css          → Master file (imports all above)
└── js/
    └── app.js             → Shared JavaScript utilities
```

---

## 🎨 Design Principles

### 1. **Subtle & Professional**
- Clean white backgrounds
- Atrios orange as tasteful accent
- No aggressive highlighting
- Professional gray text hierarchy

### 2. **Clear Comment Headers**
All files use standardized comment blocks:
```css
/* ============================================================
   SECTION: Component Name
   PURPOSE: What this does
   LAST MODIFIED: Date
   ============================================================ */
```

### 3. **Easy to Change**
Want to change colors? Just edit `variables.css`:
```css
:root {
  --atrios-orange: #F16136;  /* Change this ONE place */
}
```
All buttons, links, and accents update automatically!

---

## 🎨 Color System

### Brand Colors
- **Primary Orange**: `#F16136` (buttons, links, accents)
- **Orange Light**: `#FF8A65` (gradients)
- **Orange Dark**: `#e05530` (hover states)

### Neutrals
- **Gray 50**: `#f9fafb` (page background)
- **Gray 200**: `#e5e7eb` (borders)
- **Gray 500**: `#6b7280` (secondary text)
- **Gray 900**: `#1a1a1a` (primary text)

### Semantic Colors
- **Success**: `#10b981` (green)
- **Warning**: `#f59e0b` (amber)
- **Danger**: `#ef4444` (red)
- **Info**: `#3b82f6` (blue)

---

## 📐 Typography

### Font Family
- **Primary**: Inter (clean, professional)
- **Fallback**: System fonts (-apple-system, Segoe UI, etc.)

### Font Sizes
```css
--text-xs: 11px   (tiny labels)
--text-sm: 12px   (secondary text)
--text-base: 14px (body text)
--text-lg: 16px   (emphasis)
--text-xl: 18px   (headings)
--text-4xl: 30px  (hero text)
```

### Font Weights
```css
--font-normal: 400    (body)
--font-medium: 500    (labels)
--font-semibold: 600  (headings)
--font-bold: 700      (emphasis)
```

---

## 🎯 Components

### Buttons
```html
<button class="btn btn-primary">Primary</button>
<button class="btn btn-secondary">Secondary</button>
<button class="btn btn-outline">Outline</button>
<button class="btn btn-sm">Small</button>
```

### Cards
```html
<div class="card">
    <div class="card-header">
        <h5>Card Title</h5>
    </div>
    <div class="card-body">
        Card content here
    </div>
</div>
```

### Forms
```html
<label class="form-label">Label</label>
<input type="text" class="form-control" placeholder="Enter text">
```

### Alerts
```html
<div class="alert alert-success">Success message!</div>
<div class="alert alert-danger">Error message!</div>
```

### Badges
```html
<span class="badge badge-success">Active</span>
<span class="badge badge-warning">Pending</span>
<span class="badge badge-danger">Rejected</span>
```

---

## 🚀 Usage

### In Every Page:
```php
<?php
$pageTitle = 'Your Page Title';
require_once 'includes/header.php';
?>

<!-- Your page content here -->
<div class="card">
    <div class="card-body">
        <h2>Hello World!</h2>
        <button class="btn btn-primary">Click Me</button>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
```

---

## ✨ Benefits

### 1. **One Place to Change Everything**
Update `variables.css` → Entire app updates!

### 2. **Consistent Look**
Every button, card, form looks the same across all pages.

### 3. **Easy Maintenance**
Clear comments make finding and fixing things simple.

### 4. **No Last-Minute Disasters**
Sleek, professional UI from day 1. No panic redesigns!

### 5. **Client-Ready Anytime**
Always looks polished and professional.

---

## 📝 Best Practices

### DO:
✅ Use design system classes (`btn-primary`, `card`, etc.)  
✅ Follow comment header format  
✅ Use CSS variables for colors/spacing  
✅ Keep styles in CSS files (not inline)

### DON'T:
❌ Add inline styles (`style="color: red"`)  
❌ Create new colors (use existing variables)  
❌ Mix spacing units (use `var(--space-4)`)  
❌ Override without reason

---

## 🎨 Customization

### Change Primary Color:
Edit `variables.css`:
```css
--atrios-orange: #YOUR_COLOR;
```

### Change Font:
Edit `variables.css`:
```css
--font-primary: 'YourFont', sans-serif;
```

### Add New Component:
Add to `components.css` with clear comments:
```css
/* ============================================================
   [YOUR-COMPONENT] - Component Name
   ============================================================ */
.your-component {
  /* styles here */
}
```

---

## 📚 Resources

- **Inter Font**: https://fonts.google.com/specimen/Inter
- **Bootstrap Docs**: https://getbootstrap.com/docs/5.3/
- **Font Awesome Icons**: https://fontawesome.com/icons

---

## 🔄 Version History

- **v1.0.0** (2026-02-20): Initial design system creation
  - Variables system
  - Layout structure
  - Component library
  - Clear documentation

---

**Built with ❤️ for Atrios ATS**
