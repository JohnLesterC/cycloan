# Dashboard Styling - Visual Reference Guide

## Component Hierarchy & Layout

```
┌─────────────────────────────────────────────────────┐
│              DASHBOARD HEADER                        │
│  Welcome Back! | [Apply New Loan Button]            │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│           ACTIVE LOAN BANNER                        │
│ ┌──────┐                                  [ACTIVE] │
│ │ Icon │  Active Loan - Individual                 │
│ │  📄  │  Loan ID: LOAN-20251027-0001              │
│ └──────┘                                            │
│                                                     │
│ ┌──────────┬──────────┬──────────┬──────────┐     │
│ │ Loan Amt │Total Paid│ Remaining│ Progress │     │
│ │₱10,327.97│₱3,442.64 │₱7,060.93 │ 33.3%    │     │
│ └──────────┴──────────┴──────────┴──────────┘     │
│                                                     │
│ Progress: [========>................] 33.3%        │
│                                                     │
│ Status: Pending → Pre-Approval → Credit → Active   │
│                                                     │
│ [View Full Details] [View History]                │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│            INFO ALERT                              │
│ ℹ️  Note: You cannot apply for a new loan while... │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│         CREDIT SCORE CARD                          │
│ ┌──────┐                                            │
│ │ ⭐  │  Your Credit Score                         │
│ │      │  0 Points                                  │
│ └──────┘                                            │
│ Earn points by completing loans on time...        │
└─────────────────────────────────────────────────────┘
```

## Color Reference

```
PRIMARY ELEMENTS (Green)
├─ Header: #1b5e20
├─ Hover: #2e7d32
└─ Accent: #1a7f37

METRIC CARDS (Gradients)
├─ Loan Amount: #667eea → #764ba2 (Purple)
├─ Total Paid: #f093fb → #f5576c (Pink)
├─ Remaining: #fbc2eb → #a18cd1 (Purple)
└─ Progress: #84fab0 → #8fd3f4 (Cyan)

ALERTS (Orange)
├─ Background: #fff3e0
├─ Text: #e65100
└─ Border: #f57c00

STATUS BADGES
├─ Active: #e6ffed (bg) | #1a7f37 (text) | #1b5e20 (border)
├─ Pending: #fff3cd (bg) | #856404 (text) | #ffc107 (border)
└─ Closed: #e8eaed (bg) | #3c4043 (text) | #9aa0a6 (border)

PROGRESS BAR
├─ Background: #e0e0e0
├─ Fill: #66bb6a → #81c784 (Green)
└─ Glow: rgba(102,187,106,0.4)

BACKGROUNDS
├─ Card: #ffffff
├─ Banner: #f8fafc gradient
├─ Tracker: #f5f9ff → #f0f5ff
├─ Alert: #fff3e0 → #ffe0b2
└─ Credit: #e8f5e9 → #f1f8e9
```

## Sizing Reference

```
SPACING
├─ Major sections: 32px margin
├─ Component gap: 20px
├─ Internal padding: 20-32px
├─ Small padding: 12px
└─ Micro padding: 8px

ICONS
├─ Banner/Credit icon: 70px
├─ Metric card icon: 60px
├─ Alert icon: 1.5rem font
└─ UI icons: 28-36px

FONTS
├─ Heading 1: 1.75rem (banner title)
├─ Heading 2: 1.5rem (metric values)
├─ Heading 3: 1.25rem (section title)
├─ Large: 2.5rem (credit points)
├─ Normal: 0.95rem (body text)
├─ Small: 0.85rem (labels)
└─ Tiny: 0.75rem (indicators)

BORDERS & RADIUS
├─ Banner: 16px radius, 2px border
├─ Cards: 12px radius, 1px border
├─ Buttons: 8px radius
├─ Progress: 10px radius
└─ Badges: 20-25px radius (pills)

SHADOWS
├─ Large: 0 8px 24px rgba(0,0,0,0.12)
├─ Medium: 0 4px 12px rgba(0,0,0,0.12)
├─ Small: 0 2px 8px rgba(0,0,0,0.08)
└─ Inset: inset 0 2px 4px rgba(0,0,0,0.1)
```

## Animation Reference

```
TRANSITIONS
├─ All properties: 0.3s ease
├─ Progress bar: 0.5s ease
└─ Hover effects: instant

TRANSFORMS
├─ Hover up: translateY(-2px)
├─ Large hover: translateY(-4px)
└─ Default: translateY(0)

HOVER EFFECTS
├─ Cards: shadow increase + translate
├─ Buttons: gradient reverse + shadow
├─ Progress: color change + translate
└─ Links: underline + color change
```

## Component States

```
METRIC CARD STATES
├─ Default: White bg, gray border
├─ Hover: Shadow enhanced, border green, translate up
└─ Active: (N/A - static display)

PROGRESS STEP STATES
├─ Todo: White bg, gray border, gray text
├─ Done: Green bg, green border, green text
├─ Current: Dark green bg, white text, dot indicator
└─ Future: Same as todo

BUTTON STATES
├─ Primary Default: Green gradient, shadow
├─ Primary Hover: Darker gradient, bigger shadow, translate
├─ Secondary Default: White bg, green border, green text
├─ Secondary Hover: Green gradient bg, white text
└─ Disabled: Gray, cursor not-allowed (if applicable)

STATUS BADGE STATES
├─ Active: Green theme with border
├─ Pending: Yellow theme with border
└─ Closed: Gray theme with border
```

## Responsive Behavior

```
DESKTOP (1200px+)
├─ Metric grid: 4 columns
├─ Banner: Full width flex
├─ Sidebar: 260px fixed
└─ Full spacing: 32px

TABLET (768px - 1024px)
├─ Metric grid: 2-3 columns
├─ Banner: Stacked on small tablets
├─ Sidebar: 220px fixed
└─ Reduced spacing: 24px

MOBILE (< 768px)
├─ Metric grid: 1 column (stacked)
├─ Banner: Vertical stack
├─ Sidebar: Full-width toggle
├─ Compact spacing: 16px
└─ Buttons: Full-width

SMALL MOBILE (< 576px)
├─ All single column
├─ Minimal spacing
├─ Touch-friendly sizes (48px min)
└─ Reduced font sizes
```

## Browser Support

```
✅ Chrome/Chromium 90+
✅ Firefox 88+
✅ Safari 14+
✅ Edge 90+
✅ Mobile Safari (iOS 14+)
✅ Chrome Mobile (Android 90+)

Features Used:
├─ CSS Grid
├─ Flexbox
├─ CSS Gradients
├─ CSS Transforms
├─ CSS Transitions
├─ Box-shadow
└─ Media queries
```

## Best Practices Applied

```
PERFORMANCE
✅ Hardware acceleration (transform)
✅ Efficient selectors
✅ Minimal repaints
✅ Optimized animations

ACCESSIBILITY
✅ High contrast ratios
✅ Large touch targets (48px)
✅ Semantic color coding
✅ Clear visual hierarchy

MAINTAINABILITY
✅ Organized CSS structure
✅ Consistent naming (BEM-like)
✅ Reusable classes
✅ Well-documented colors

DESIGN CONSISTENCY
✅ Unified spacing system
✅ Consistent shadows
✅ Color palette adherence
✅ Typography hierarchy
```

## Implementation Notes

For developers implementing this dashboard:

1. **Ensure proper HTML structure** with correct class names
2. **Use semantic color meanings** (green for success, orange for warning)
3. **Test all interactions** on various devices
4. **Monitor performance** of animations
5. **Maintain color consistency** across the application
6. **Follow the spacing guide** for new components
7. **Use gradients responsibly** for performance

---

*Last Updated: November 2, 2025*
*Dashboard Version: 2.0 (Complete Styling)*
*CSS File Size: 1202 lines*
*Production Ready: ✅ YES*
