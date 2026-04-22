---
name: Modern Dashboard Design System
colors:
  surface: '#0b141c'
  surface-dim: '#0b141c'
  surface-bright: '#313a43'
  surface-container-lowest: '#060f16'
  surface-container-low: '#141c24'
  surface-container: '#182028'
  surface-container-high: '#222b33'
  surface-container-highest: '#2d363e'
  on-surface: '#dae3ee'
  on-surface-variant: '#c2cab0'
  inverse-surface: '#dae3ee'
  inverse-on-surface: '#29313a'
  outline: '#8c947c'
  outline-variant: '#424936'
  surface-tint: '#98da27'
  primary: '#ccff80'
  on-primary: '#213600'
  primary-container: '#a3e635'
  on-primary-container: '#416400'
  inverse-primary: '#446900'
  secondary: '#c1c7d0'
  on-secondary: '#2b3138'
  secondary-container: '#41474f'
  on-secondary-container: '#b0b5be'
  tertiary: '#eaeef8'
  on-tertiary: '#2c3138'
  tertiary-container: '#ced2dc'
  on-tertiary-container: '#555a62'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#b2f746'
  primary-fixed-dim: '#98da27'
  on-primary-fixed: '#121f00'
  on-primary-fixed-variant: '#334f00'
  secondary-fixed: '#dde3ec'
  secondary-fixed-dim: '#c1c7d0'
  on-secondary-fixed: '#161c23'
  on-secondary-fixed-variant: '#41474f'
  tertiary-fixed: '#dee2ec'
  tertiary-fixed-dim: '#c2c7d0'
  on-tertiary-fixed: '#171c23'
  on-tertiary-fixed-variant: '#42474f'
  background: '#0b141c'
  on-background: '#dae3ee'
  surface-variant: '#2d363e'
typography:
  h1:
    fontFamily: Space Grotesk
    fontSize: 40px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  h2:
    fontFamily: Space Grotesk
    fontSize: 32px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  h3:
    fontFamily: Space Grotesk
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.5'
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.5'
  label-caps:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '700'
    lineHeight: '1'
    letterSpacing: 0.05em
  stat-value:
    fontFamily: Space Grotesk
    fontSize: 48px
    fontWeight: '700'
    lineHeight: '1'
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  xs: 4px
  sm: 12px
  md: 24px
  lg: 40px
  xl: 64px
  gutter: 24px
  margin: 32px
---

## Brand & Style

This design system is engineered for high-performance administrative environments where data density and rapid cognitive processing are paramount. The aesthetic is **High-Contrast Modern**, leaning into a "command center" atmosphere that feels both technical and premium. It targets developers, system administrators, and data analysts who require a focused, low-strain interface for long-duration monitoring.

The visual language balances the deep, obsidian-like depths of the background with a high-energy lime accent. This creates a clear visual hierarchy where the most important interactive elements and status indicators demand immediate attention against a silent, professional backdrop. The tone is precise, authoritative, and sophisticated.

## Colors

The palette is built on a foundation of deep blacks and charcoal grays to minimize eye fatigue. The primary accent, **Lime Green (#a3e635)**, is reserved strictly for primary actions, success states, and critical data points. 

Secondary and tertiary grays provide necessary variance for borders and hover states without breaking the dark-mode immersion. Use the background color for the main application canvas and the card color for all grouped content areas. Semantic colors for error (red) and warning (amber) should maintain the same high-vibrancy saturation as the primary lime to ensure consistent visual weight across all status types.

## Typography

This design system utilizes a dual-font strategy. **Space Grotesk** is used for headlines and large data visualizations to provide a technical, geometric edge that aligns with the dashboard's administrative nature. **Inter** handles all body copy and UI labels, chosen for its exceptional legibility at small sizes and its neutral, systematic character.

Maintain tight tracking on headlines to emphasize the modern, compact feel. Use the uppercase label style for section headers and table headers to create a clear structural distinction from interactive content.

## Layout & Spacing

The layout operates on an 8px rhythmic grid. It employs a **12-column fluid grid system** for the main content area, allowing widgets and cards to scale dynamically across various screen sizes. Gutters are fixed at 24px to ensure distinct separation between data-heavy containers.

Padding within cards should follow the 'md' (24px) spacing token to provide enough "breathable" space, preventing the UI from feeling cluttered despite high information density. Sidebars should be fixed-width (280px) to maintain consistent navigation access, while the main stage expands to fill the viewport.

## Elevation & Depth

In this design system, depth is conveyed through **Tonal Layering** and **Low-Contrast Outlines** rather than traditional shadows. Shadows are often lost in near-black environments, so we differentiate surfaces by progressively lightening the background color.

- **Level 0 (Background):** #0a0c10 (The furthest back)
- **Level 1 (Cards/Panels):** #161b22 with a 1px solid border of #30363d.
- **Level 2 (Modals/Popovers):** #1c2128 with a subtle glow (0px 4px 20px rgba(0,0,0,0.5)).

Interactive elements like search inputs should use a subtle inner stroke to appear recessed, while primary buttons appear to sit on top of the surface through their high-luminance lime color.

## Shapes

The shape language is defined by **Rounded** geometry. This softens the technical "hardness" of the dark theme and makes the interface feel more accessible. 

- **Containers & Cards:** 1rem (rounded-lg) corner radius.
- **Buttons & Chips:** Full pill-shape (3rem) to maximize the "high contrast" clickable feel requested.
- **Inputs:** 0.5rem (rounded) to maintain a balance between the sharp grid and the pill-shaped buttons.

Consistency in corner radii is critical; avoid mixing sharp and rounded elements within the same component group.

## Components

### Buttons
Primary buttons are pill-shaped, using the Lime Green (#a3e635) background with black text for maximum contrast. Hover states should involve a slight brightness increase or a subtle outer glow of the same color. Secondary buttons use a ghost style with a #30363d border and white text.

### Statistic Cards
These feature a large "stat-value" headline in Space Grotesk. A small sparkline chart (in Lime Green) should be positioned at the bottom or side of the card. Use a "label-caps" font for the metric title.

### Search Inputs
Inputs use the #0a0c10 background (recessed into the card) with a 1px border. Upon focus, the border transitions to Lime Green. Include a subtle search icon (20px) on the left with 50% opacity.

### User Profile Cards & Badges
User cards should feature a circular avatar. Status badges are small, solid circles positioned at the bottom-right of the avatar. 
- **Online:** #a3e635 (Lime)
- **Idle:** #eab308 (Amber)
- **Offline:** #484f58 (Gray)
Badges must include a 2px stroke matching the card background (#161b22) to ensure they "pop" against the avatar image.

### Chips & Tags
Use for categories or metadata. These should be pill-shaped with a tertiary gray background and small 'body-sm' text. For active filters, use a subtle lime-tinted background (alpha 15%) with lime text.