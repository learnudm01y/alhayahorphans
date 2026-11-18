---
name: Relief & Sponsorship ERP
colors:
  surface: '#faf8ff'
  surface-dim: '#d0d8ff'
  surface-bright: '#faf8ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f3f2ff'
  surface-container: '#ebedff'
  surface-container-high: '#e3e7ff'
  surface-container-highest: '#dce1ff'
  on-surface: '#00164e'
  on-surface-variant: '#3f4940'
  inverse-surface: '#05297a'
  inverse-on-surface: '#eff0ff'
  outline: '#6f7a6f'
  outline-variant: '#becabd'
  surface-tint: '#006d37'
  primary: '#006130'
  on-primary: '#ffffff'
  primary-container: '#107c41'
  on-primary-container: '#b6ffc5'
  inverse-primary: '#7ada95'
  secondary: '#226a53'
  on-secondary: '#ffffff'
  secondary-container: '#a7eed1'
  on-secondary-container: '#276e57'
  tertiary: '#735c00'
  on-tertiary: '#ffffff'
  tertiary-container: '#cca72f'
  on-tertiary-container: '#4e3d00'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#96f7af'
  primary-fixed-dim: '#7ada95'
  on-primary-fixed: '#00210c'
  on-primary-fixed-variant: '#005228'
  secondary-fixed: '#aaf1d4'
  secondary-fixed-dim: '#8ed5b9'
  on-secondary-fixed: '#002117'
  on-secondary-fixed-variant: '#00513d'
  tertiary-fixed: '#ffe088'
  tertiary-fixed-dim: '#e9c349'
  on-tertiary-fixed: '#241a00'
  on-tertiary-fixed-variant: '#574500'
  background: '#faf8ff'
  on-background: '#00164e'
  surface-variant: '#dce1ff'
typography:
  headline-xl:
    fontFamily: Plus Jakarta Sans
    fontSize: 2rem
    fontWeight: '700'
    lineHeight: 2.5rem
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 1.5rem
    fontWeight: '600'
    lineHeight: 2rem
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 1.25rem
    fontWeight: '600'
    lineHeight: 1.75rem
  title-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 1rem
    fontWeight: '600'
    lineHeight: 1.5rem
  body-lg:
    fontFamily: Inter
    fontSize: 1rem
    fontWeight: '400'
    lineHeight: 1.625rem
  body-md:
    fontFamily: Inter
    fontSize: 0.875rem
    fontWeight: '400'
    lineHeight: 1.375rem
  body-sm:
    fontFamily: Inter
    fontSize: 0.75rem
    fontWeight: '400'
    lineHeight: 1.125rem
  label-md:
    fontFamily: Inter
    fontSize: 0.8125rem
    fontWeight: '500'
    lineHeight: 1.125rem
  label-sm:
    fontFamily: Inter
    fontSize: 0.6875rem
    fontWeight: '600'
    lineHeight: 0.875rem
  numeric-table:
    fontFamily: Inter
    fontSize: 0.8125rem
    fontWeight: '500'
    lineHeight: 1.25rem
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1rem
  margin: 1.5rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 0.75rem
  space-lg: 1.25rem
  space-xl: 1.75rem
---

## Brand & Style

This design system is tailored for advanced desktop ERP systems managing charitable endowments, humanitarian aid, orphan sponsorships, and field field operations. The core brand personality communicates institutional trust, operational speed, dignified social welfare, and mathematical accuracy. 

The visual style merges Modern Enterprise SaaS with high-clarity data architecture. While legacy systems (such as Microsoft Access desktop databases) relied on crowded, unstyled inputs and abrasive raw contrasts, this system modernizes complex workflow forms and dense tables into an organized, calming, and human-centric Arabic RTL desktop application. 

Key attributes:
- **Tone:** Authoritative, transparent, empathetic, and systematically ordered.
- **Visual Stance:** Ultra-crisp data hierarchy, subtle surface separation, reduced cognitive strain over prolonged shifts (8+ hours of continuous casework input), and purposeful emerald and gold accents denoting benevolence and institutional prestige.
- **Directionality:** Native right-to-left (RTL) baseline across all controls, indicators, data streams, and hierarchical breadcrumbs.

## Colors

The color palette establishes an executive, philanthropic identity centered around emerald and deep institutional greens, balanced with soft warm gold accents for critical statuses and calm navy blues for structural navigation.

- **Primary (`#107C41`):** Forest Relief Green. Used for primary execution triggers, active sponsorship badges, system confirmation states, and key navigation highlights.
- **Secondary (`#0D5C46`):** Deep Emerald. Establishes core brand containers, table header backdrops, high-level reporting headers, and high-emphasis interface states.
- **Tertiary (`#D4AF37`):** Warm Gold. Reserved for premium sponsorship tiers, pending orphan allocations, donor gratitude alerts, and priority action anchors.
- **Neutral Accent (`#1E3A8A`):** Deep Indigo Navy. Anchors persistent sidebar rails, operational metadata tags, filter group dividers, and reference ID displays.

### Background & Surface Hierarchy
- **Canvas Base (`#F8FAFC`):** A clean slate neutral that prevents glare on high-resolution desktop panels.
- **Surface Elevation (`#FFFFFF`):** Pure white container surfaces for form cards, data grids, and slide-over drawers.
- **Border Dim (`#E2E8F0`):** Precise 1px hairline dividers that eliminate the harsh, thick gridlines seen in legacy database tables.
- **Text Primary (`#0F172A`):** Deep slate-black for high legibility Arabic text rendering.
- **Text Muted (`#64748B`):** Supporting metadata labels, secondary IDs, and inactive field placeholders.

## Typography

Typography prioritizes Arabic reading comfort, compact horizontal space-efficiency, and numeral clarity across dense tabular layouts. 

- **Display & Headlines (Plus Jakarta Sans):** Selected for its friendly geometric contours, contemporary presence, and balanced open counters that pair naturally with modern Arabic typefaces (e.g., Cairo, Readex Pro, or Noto Sans Arabic fallbacks).
- **Body & Data Grid (Inter):** Serves operational density. Its tall x-height and tabular numeral features (`tnum`) ensure national identity numbers (رقم الهوية), Palestinian Palpay accounts, IBAN records, phone extensions, and monetary figures line up with mathematical precision in Arabic and Latin character mixing.
- **RTL Fluidity:** Line-height tokens include deliberate padding cushions (`1.375rem` for 14px text) to prevent diacritic clipping common in Arabic ligatures.

## Layout & Spacing

The layout model is architected as an industrial desktop application, built around persistent multi-zone navigation, compact query bars, and multi-paned operational split views.

### Architectural Canvas
- **Persistent RTL Navigation Sidebar:** 260px fixed right bar containing charity program directories (سجلات الأيتام، الكفالات، توزيع الطرود، المساعدات النقدية).
- **Application Workzone:** Flexible canvas stretching across standard enterprise desktop resolutions (1440px to 4K displays).
- **Master-Detail Workspace:** Dual-pane split ratio (40% query filters and beneficiary summary card / 60% dynamic ledger & case history).

### Rhythm & Density
- Micro-steps (`space-xs` = 4px, `space-sm` = 8px) drive dense table cells and stacked form fields, minimizing vertical scrolling.
- `space-md` (12px) structures card interior grids and inline form groups.
- `space-lg` (20px) creates definitive breaks between filter headers and data-grid views without sacrificing spatial economy.

## Elevation & Depth

To avoid the cluttered, flat appearance of legacy forms and the muddy visual noise of heavy drop shadows, this design system uses **structured surface-layering and low-contrast ambient diffusion**.

1. **Level 0 (App Shell / Canvas):** Neutral `#F8FAFC`. Houses the application baseline and inactive grid borders.
2. **Level 1 (Card & Section Containers):** Pure `#FFFFFF` resting over Level 0, bordered by a soft 1px hairline (`#E2E8F0`). Shadow: `0 1px 3px rgba(15, 23, 42, 0.04)`.
3. **Level 2 (Data Grids & Interactive Panels):** Elevated data regions featuring subtle vertical separation: `0 4px 6px -1px rgba(15, 23, 42, 0.06), 0 2px 4px -2px rgba(15, 23, 42, 0.03)`.
4. **Level 3 (Floating Action Toolbars & Contextual Modals):** Used for print preview sheets (كشوفات الموردين), voucher approvals (قسائم شرائية), and beneficiary lookup drawers. Shadow: `0 12px 24px -4px rgba(13, 92, 70, 0.08), 0 4px 8px -2px rgba(15, 23, 42, 0.04)`.

## Shapes

The shape system adopts a **Soft (Level 1)** aesthetic. Standard interface elements maintain a disciplined `0.25rem` (4px) corner radius, while cards and outer container modules use `0.5rem` (8px). 

This subtle soft curvature:
- Retains the rigorous geometry expected of robust, institutional enterprise software.
- Distinguishes itself decisively from the sharp, utilitarian edge-clutter of legacy Windows Access forms.
- Preserves tabular compactness without excessive padding waste.
- Keeps pill shapes (`rounded-full`) exclusively for contextual status pills (e.g., مستلم، قيد الانتظار، معاق، أيتام).

## Components

### 1. Data Grids (جداول البيانات)
- **Header:** Background in Secondary Deep Emerald (`#0D5C46`) with pure white bold text, or a light tint `#F1F5F9` with slate `#334155` headers for secondary sub-ledgers.
- **Row Styling:** Alternating striped rows (`#FFFFFF` and `#F8FAFC`) with subtle hover states (`#F0FDF4`). 
- **Zebra Bordering:** Soft 1px horizontal dividers (`#E2E8F0`), zero vertical borders to optimize scan speed across 15+ column beneficiary sets.
- **Inline Actions:** Compact action icons grouped on the leftmost side of the RTL layout (edit, delete, generate voucher).

### 2. Form Inputs & Comboboxes (حقول الإدخال والاختيار)
- **Container:** Pure `#FFFFFF` background with 1px border `#CBD5E1`, 36px standard desktop height.
- **Focus State:** 2px ring in Primary Forest Green (`#107C41`) with soft 10% opacity glow; no outline displacement.
- **Labels:** Positioned atop inputs in bold RTL alignment (`label-md`), accompanied by subtle red requirement asterisks (`#DC2626`).

### 3. Action Buttons (أزرار العمليات)
- **Primary (حفظ / إضافة جديد):** Solid `#107C41` fill with white text, transitioning to `#0D5C46` on hover.
- **Secondary (تصدير كشف / طباعة قسيمة):** `#FFFFFF` fill with 1px `#CBD5E1` border and `#1E3A8A` text.
- **Warning / Delete (حذف / إلغاء):** Subdued soft red `#FEF2F2` container with `#DC2626` text, escalating to solid red only on modal confirmation.

### 4. Status Chips & Badges (الشارات والوسوم)
- **Received (مستلم):** Emerald tint (`#DCFCE7` fill, `#15803D` text).
- **Pending Sponsorship (بانتظار كفالة):** Gold tint (`#FEF9C3` fill, `#854D0E` text).
- **Special Needs / Medical (احتياجات خاصة / مريض):** Indigo tint (`#E0E7FF` fill, `#3730A3` text).

### 5. Beneficiary Identity Cards (بطاقة بيانات المستفيد)
- Designed with prominent national ID highlights, inline family member counters (عدد الذكور، عدد الإناث، إجمالي الأفراد), dynamic Palpay / bank account pills, and instant photo thumbnail containers for humanitarian verification documents.