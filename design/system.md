# BarberHub design system extraction

Production sources reviewed: `resources/css/public-booking.css`, `resources/css/tenant-branding.css`, `resources/views/components/public-booking-layout.blade.php`, `resources/views/components/customer-brand.blade.php`, and `app/Support/TenantBranding.php`.

This is an extraction, not a proposed visual system. Values below are present in production sources. `resources/css/tenant-branding.css` also contains dark controls for an internal branding-editor preview; those editor-only colors are intentionally excluded from the customer-facing tokens here.

## Colors

| Token | Value | Usage | Source |
|---|---|---|---|
| `--bh-brand-primary` | Tenant-provided uppercase `#RRGGBB`; fallback `#496C35` | Primary action, selected options, brand surfaces | `app/Support/TenantBranding.php`; applied by `resources/views/components/public-booking-layout.blade.php` |
| `--bh-brand-primary-ink` | Computed `#000000` or `#FFFFFF` | Accessible foreground on tenant primary | `app/Support/TenantBranding.php` |
| `--bh-brand-accent` | Tenant-provided uppercase `#RRGGBB`; fallback `#B8885A` | Tenant accent action/swatch | `app/Support/TenantBranding.php`; `resources/css/tenant-branding.css` |
| `--bh-brand-accent-ink` | Computed `#000000` or `#FFFFFF` | Accessible foreground on tenant accent | `app/Support/TenantBranding.php` |
| `--bh-brand-surface` | `#F8F7F4` | Public booking/customer canvas | `app/Support/TenantBranding.php` |
| `--bh-public-border` | `#E5E4DF` | Public booking panel and control outlines | `resources/css/public-booking.css` |
| `--bh-public-muted` | `#6B7280` | Secondary public booking copy | `resources/css/public-booking.css` |
| `public ink` | `#141413` | Public booking primary text | `resources/css/public-booking.css` |
| `public muted ink` | `#52525B` | Secondary verification copy | `resources/css/public-booking.css` |
| `customer ink` | `#1F2937` | Customer-brand text | `resources/css/tenant-branding.css` |
| `customer description` | `#374151` | Customer-brand description text | `resources/css/tenant-branding.css` |
| `customer cover fallback` | `#E7E5DF`; empty `#EAE8E2` | Customer-brand cover placeholder | `resources/css/tenant-branding.css` |
| `public panel` | `#FFFFFF` | Booking cards, controls | `resources/css/public-booking.css` |
| `focus outline` | `#27272A` | Keyboard focus and secondary text | `resources/css/public-booking.css` |
| `selected choice tint` | Primary mixed at 6% with white | Selected booking option | `resources/css/public-booking.css` |

Tenant primary/accent colors are runtime values and must remain represented as CSS custom properties; screenshots or fallback values do not replace the tenant variables.

## Typography

- The live `/book` stylesheet declares self-hosted `Plus Jakarta Sans` at weights 400, 500, 600 and 700, with `ui-sans-serif, system-ui, sans-serif` fallback. Source: `resources/css/public-booking.css`.
- The live `/book` editorial headline also declares self-hosted italic `Instrument Serif` with `Georgia, serif` fallback. Source: `resources/css/public-booking.css`.
- Customer-brand typography inherits its host family. Source: `resources/css/tenant-branding.css`.
- This design direction uses only the system stack `ui-sans-serif, system-ui, sans-serif` as required for portable, self-contained artboards. It does not embed or fetch the production local font assets.
- Public booking base size is `.875rem`, line-height `1.5`; its principal heading scales from `2rem` to `2.625rem`, with `1.1` line-height. Source: `resources/css/public-booking.css`.

## Spacing

These observed scale values are used in production styles; not every component uses every value.

| Value | Observed use | Source |
|---|---|---|
| `.25rem` | Fine inline/label spacing | `resources/css/public-booking.css` |
| `.5rem` | Control gaps and compact padding | `resources/css/public-booking.css` |
| `.75rem` | Header/control gaps and button padding | `resources/css/public-booking.css` |
| `1rem` | Main gutters and form spacing | `resources/css/public-booking.css` |
| `1.25rem` | Panel padding and section rhythm | `resources/css/public-booking.css` |
| `1.5rem` | Customer content padding and public panel radius-scale spacing | `resources/css/public-booking.css`; `resources/css/tenant-branding.css` |
| `2rem` | Editorial layout rhythm | `resources/css/public-booking.css` |
| `2.5rem` | Booking flow section gap | `resources/css/public-booking.css` |

## Radius

| Value | Observed use | Source |
|---|---|---|
| `.625rem` | Customer action controls | `resources/css/tenant-branding.css` |
| `.75rem` | Booking fields and buttons | `resources/css/public-booking.css` |
| `.875rem` | Customer logo; internal editor panel (the latter not used on customer surface) | `resources/css/tenant-branding.css` |
| `1rem` | Booking media/map/dialog | `resources/css/public-booking.css` |
| `1.125rem` | Booking choice rows | `resources/css/public-booking.css` |
| `1.5rem` | Booking panels | `resources/css/public-booking.css` |
| `2rem` | Booking editorial intro | `resources/css/public-booking.css` |
| `9999px` | Customer avatar/logo placeholder circle | `resources/css/tenant-branding.css` |

## Shadows

| Value | Observed use | Source |
|---|---|---|
| `0 22px 55px rgb(31 41 35 / 12%)` | Editorial booking intro | `resources/css/public-booking.css` |
| `0 12px 34px rgb(30 48 34 / 5%)` | Booking panel | `resources/css/public-booking.css` |
| `0 8px 22px rgb(30 48 34 / 6%)` | Hovered booking choice | `resources/css/public-booking.css` |

## Component and behavior rules

- Public booking uses a light tenant surface, dark legible text, a compact tenant identity header, and a centered content width of `68rem`. Source: `resources/css/public-booking.css`.
- Primary actions and selected states use tenant primary with its computed readable ink; accents use tenant accent and computed ink. Keep both variables live at runtime. Sources: `app/Support/TenantBranding.php`, `resources/views/components/public-booking-layout.blade.php`.
- Customer profile identity should remain tenant-specific. The existing customer-brand convention uses initials on a primary-colored square/circle if no logo is present, and tenant logo assets when available. Source: `resources/views/components/customer-brand.blade.php`, `resources/css/tenant-branding.css`.
- Booking uses white panels on the warm light surface, thin neutral borders, moderate corner radii, compact readable secondary text, and strong selected states. Source: `resources/css/public-booking.css`.
- Keep history as individually scannable rows and preference/reminder controls as semantically labeled native form controls. Use visible keyboard focus and responsive single-column behavior on narrow widths, consistent with public booking patterns. Source conventions: `resources/css/public-booking.css`.
- The customer-profile visual order is pinned by `references/image.png`: customer initials/name and membership/visit summary first; appointment history next; salon preferences after history; periodic reminders below preferences. Reference content is a design cue, not application data or a source of color/font tokens.
