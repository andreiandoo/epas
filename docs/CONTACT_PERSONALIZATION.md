# Contact Page Personalization Guide

This document explains how to implement and customize contact pages and contact information throughout tenant templates.

---

## Overview

Contact personalization includes:
1. **Contact Details** - Address, phone, email, hours
2. **Contact Form** - Customizable inquiry forms
3. **Maps Integration** - Google Maps or alternative
4. **Social Links** - Already in config.social
5. **Location Blocks** - For multi-location businesses

---

## Part 1: Contact Configuration Model

### Proposed ContactConfig Interface

Add to `TixelloConfig` or as a separate API endpoint:

```typescript
interface ContactConfig {
    // Primary contact
    email: string;
    phone: string | null;
    whatsapp: string | null;

    // Location
    address: {
        street: string;
        city: string;
        state: string;
        postalCode: string;
        country: string;
        formatted: string;  // Full formatted address
    } | null;

    // Coordinates for map
    coordinates: {
        lat: number;
        lng: number;
    } | null;

    // Business hours
    businessHours: {
        [day: string]: {
            open: string;   // "09:00"
            close: string;  // "18:00"
            closed: boolean;
        };
    } | null;

    // Form settings
    contactForm: {
        enabled: boolean;
        subjects: string[];  // Dropdown options
        recipientEmail: string;
        showPhoneField: boolean;
        requirePhone: boolean;
    };

    // Additional locations (for multi-venue)
    additionalLocations: Array<{
        id: string;
        name: string;
        address: string;
        phone: string | null;
        email: string | null;
        coordinates: { lat: number; lng: number } | null;
        hours: string | null;
    }>;
}
```

---

## Part 2: Contact Page Blocks

### Contact Info Block

```typescript
{
    id: 'contact_info_1',
    type: 'contact-info',
    settings: {
        layout: 'horizontal' | 'vertical' | 'cards',
        showIcons: true,
        showMap: true,
        mapHeight: 400,  // pixels
        mapStyle: 'default' | 'dark' | 'light' | 'satellite',
    },
    content: {
        en: {
            title: 'Get in Touch',
            subtitle: 'We\'d love to hear from you',
            emailLabel: 'Email Us',
            phoneLabel: 'Call Us',
            addressLabel: 'Visit Us',
            hoursLabel: 'Business Hours',
        }
    }
}
```

### Contact Info Block Renderer

```typescript
PageBuilderModule.registerRenderer('contact-info', (block, config) => {
    const title = PageBuilderModule.getContent(block, 'title', 'Contact Us');
    const contact = config.contact; // From TixelloConfig
    const settings = block.settings;

    const mapHtml = settings.showMap && contact?.coordinates
        ? `<div id="contact-map" class="rounded-lg overflow-hidden" style="height: ${settings.mapHeight || 400}px"
                data-lat="${contact.coordinates.lat}"
                data-lng="${contact.coordinates.lng}">
           </div>`
        : '';

    return `
        <section class="py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900">${title}</h2>
                </div>

                <div class="${settings.layout === 'horizontal' ? 'flex flex-col lg:flex-row gap-12' : 'space-y-8'}">
                    <!-- Contact Details -->
                    <div class="${settings.layout === 'horizontal' ? 'lg:w-1/3' : ''}">
                        ${contact?.email ? `
                            <div class="flex items-start gap-4 mb-6">
                                ${settings.showIcons ? `
                                    <div class="flex-shrink-0 w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
                                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                ` : ''}
                                <div>
                                    <h3 class="font-semibold text-gray-900">Email</h3>
                                    <a href="mailto:${contact.email}" class="text-primary hover:underline">${contact.email}</a>
                                </div>
                            </div>
                        ` : ''}

                        ${contact?.phone ? `
                            <div class="flex items-start gap-4 mb-6">
                                ${settings.showIcons ? `
                                    <div class="flex-shrink-0 w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
                                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                        </svg>
                                    </div>
                                ` : ''}
                                <div>
                                    <h3 class="font-semibold text-gray-900">Phone</h3>
                                    <a href="tel:${contact.phone}" class="text-primary hover:underline">${contact.phone}</a>
                                </div>
                            </div>
                        ` : ''}

                        ${contact?.address ? `
                            <div class="flex items-start gap-4 mb-6">
                                ${settings.showIcons ? `
                                    <div class="flex-shrink-0 w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center">
                                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </div>
                                ` : ''}
                                <div>
                                    <h3 class="font-semibold text-gray-900">Address</h3>
                                    <p class="text-gray-600">${contact.address.formatted}</p>
                                </div>
                            </div>
                        ` : ''}
                    </div>

                    <!-- Map -->
                    <div class="${settings.layout === 'horizontal' ? 'lg:w-2/3' : ''}">
                        ${mapHtml}
                    </div>
                </div>
            </div>
        </section>
    `;
});
```

---

## Part 3: Contact Form Block

### Contact Form Configuration

```typescript
{
    id: 'contact_form_1',
    type: 'contact-form',
    settings: {
        layout: 'standard' | 'side-by-side' | 'minimal',
        showSubject: true,
        subjects: ['General Inquiry', 'Event Question', 'Partnership', 'Support'],
        showPhone: true,
        requirePhone: false,
        showMessage: true,
        submitEndpoint: '/api/contact',
        redirectUrl: '/thank-you',
        backgroundColor: 'white' | 'gray' | 'primary',
    },
    content: {
        en: {
            title: 'Send Us a Message',
            subtitle: 'Fill out the form below and we\'ll get back to you',
            nameLabel: 'Your Name',
            namePlaceholder: 'John Doe',
            emailLabel: 'Your Email',
            emailPlaceholder: 'john@example.com',
            phoneLabel: 'Phone Number',
            phonePlaceholder: '+1 (555) 000-0000',
            subjectLabel: 'Subject',
            subjectPlaceholder: 'Select a subject',
            messageLabel: 'Your Message',
            messagePlaceholder: 'How can we help you?',
            submitText: 'Send Message',
            successMessage: 'Thank you! We\'ll be in touch soon.',
            errorMessage: 'Something went wrong. Please try again.',
        }
    }
}
```

### Contact Form Block Renderer

```typescript
PageBuilderModule.registerRenderer('contact-form', (block, config) => {
    const settings = block.settings;
    const content = block.content[PageBuilderModule.currentLanguage] || block.content['en'] || {};

    const bgClass = {
        white: 'bg-white',
        gray: 'bg-gray-50',
        primary: 'bg-primary text-white',
    }[settings.backgroundColor] || 'bg-white';

    const subjectOptions = (settings.subjects || []).map(s =>
        `<option value="${s}">${s}</option>`
    ).join('');

    return `
        <section class="py-16 ${bgClass}">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold mb-2">${content.title || 'Contact Us'}</h2>
                    ${content.subtitle ? `<p class="text-gray-600">${content.subtitle}</p>` : ''}
                </div>

                <form id="contact-form-${block.id}" class="space-y-6"
                      data-endpoint="${settings.submitEndpoint || '/api/contact'}"
                      data-redirect="${settings.redirectUrl || ''}">

                    <!-- Name -->
                    <div>
                        <label for="name-${block.id}" class="block text-sm font-medium mb-2">
                            ${content.nameLabel || 'Name'} *
                        </label>
                        <input type="text" id="name-${block.id}" name="name" required
                               placeholder="${content.namePlaceholder || ''}"
                               class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email-${block.id}" class="block text-sm font-medium mb-2">
                            ${content.emailLabel || 'Email'} *
                        </label>
                        <input type="email" id="email-${block.id}" name="email" required
                               placeholder="${content.emailPlaceholder || ''}"
                               class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    ${settings.showPhone ? `
                        <!-- Phone -->
                        <div>
                            <label for="phone-${block.id}" class="block text-sm font-medium mb-2">
                                ${content.phoneLabel || 'Phone'} ${settings.requirePhone ? '*' : ''}
                            </label>
                            <input type="tel" id="phone-${block.id}" name="phone"
                                   ${settings.requirePhone ? 'required' : ''}
                                   placeholder="${content.phonePlaceholder || ''}"
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    ` : ''}

                    ${settings.showSubject && settings.subjects?.length > 0 ? `
                        <!-- Subject -->
                        <div>
                            <label for="subject-${block.id}" class="block text-sm font-medium mb-2">
                                ${content.subjectLabel || 'Subject'} *
                            </label>
                            <select id="subject-${block.id}" name="subject" required
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">${content.subjectPlaceholder || 'Select...'}</option>
                                ${subjectOptions}
                            </select>
                        </div>
                    ` : ''}

                    ${settings.showMessage !== false ? `
                        <!-- Message -->
                        <div>
                            <label for="message-${block.id}" class="block text-sm font-medium mb-2">
                                ${content.messageLabel || 'Message'} *
                            </label>
                            <textarea id="message-${block.id}" name="message" rows="5" required
                                      placeholder="${content.messagePlaceholder || ''}"
                                      class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        </div>
                    ` : ''}

                    <!-- Submit -->
                    <div>
                        <button type="submit"
                                class="w-full px-6 py-4 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark transition">
                            ${content.submitText || 'Send'}
                        </button>
                    </div>

                    <!-- Messages -->
                    <div id="form-success-${block.id}" class="hidden p-4 bg-green-100 text-green-700 rounded-lg">
                        ${content.successMessage || 'Thank you for your message!'}
                    </div>
                    <div id="form-error-${block.id}" class="hidden p-4 bg-red-100 text-red-700 rounded-lg">
                        ${content.errorMessage || 'Something went wrong.'}
                    </div>
                </form>
            </div>
        </section>
    `;
});
```

---

## Part 4: Map Integration

### Google Maps Block

```typescript
{
    id: 'map_1',
    type: 'map',
    settings: {
        provider: 'google' | 'mapbox' | 'openstreetmap',
        height: 400,
        zoom: 15,
        style: 'default' | 'dark' | 'light' | 'satellite',
        showMarker: true,
        markerColor: '#FF0000',
        enableControls: true,
        enableScrollZoom: false,
        // For multiple locations
        locations: [
            {
                lat: 44.4268,
                lng: 26.1025,
                title: 'Main Office',
                address: 'Strada Example 123, Bucharest',
            }
        ]
    },
    content: {
        en: {
            title: 'Find Us',
            subtitle: 'Visit our office',
        }
    }
}
```

### Map Block Renderer

```typescript
PageBuilderModule.registerRenderer('map', (block, config) => {
    const settings = block.settings;
    const locations = settings.locations || [];

    // Use contact coordinates as fallback
    if (locations.length === 0 && config.contact?.coordinates) {
        locations.push({
            lat: config.contact.coordinates.lat,
            lng: config.contact.coordinates.lng,
            title: config.site?.title || 'Location',
            address: config.contact?.address?.formatted || '',
        });
    }

    const title = PageBuilderModule.getContent(block, 'title', '');

    return `
        <section class="py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                ${title ? `
                    <div class="text-center mb-8">
                        <h2 class="text-3xl font-bold text-gray-900">${title}</h2>
                    </div>
                ` : ''}

                <div id="map-${block.id}"
                     class="rounded-xl overflow-hidden shadow-lg"
                     style="height: ${settings.height || 400}px"
                     data-provider="${settings.provider || 'google'}"
                     data-zoom="${settings.zoom || 15}"
                     data-style="${settings.style || 'default'}"
                     data-locations='${JSON.stringify(locations)}'
                     data-controls="${settings.enableControls !== false}"
                     data-scroll-zoom="${settings.enableScrollZoom || false}">
                    <!-- Map will be initialized by JavaScript -->
                    <div class="h-full bg-gray-200 flex items-center justify-center">
                        <span class="text-gray-500">Loading map...</span>
                    </div>
                </div>
            </div>
        </section>
    `;
});
```

### Map Initialization Script

```typescript
// Add to PageBuilderModule or separate MapModule
function initializeMaps(): void {
    document.querySelectorAll('[id^="map-"]').forEach(async (mapEl) => {
        const provider = mapEl.dataset.provider || 'google';
        const locations = JSON.parse(mapEl.dataset.locations || '[]');
        const zoom = parseInt(mapEl.dataset.zoom || '15');

        if (locations.length === 0) return;

        const center = locations[0];

        switch (provider) {
            case 'google':
                await initGoogleMap(mapEl as HTMLElement, center, locations, zoom);
                break;
            case 'openstreetmap':
                await initLeafletMap(mapEl as HTMLElement, center, locations, zoom);
                break;
        }
    });
}

async function initGoogleMap(
    container: HTMLElement,
    center: { lat: number; lng: number },
    locations: any[],
    zoom: number
): Promise<void> {
    // Load Google Maps script if not loaded
    if (!window.google?.maps) {
        // Load script dynamically
    }

    const map = new google.maps.Map(container, {
        center,
        zoom,
        disableDefaultUI: container.dataset.controls === 'false',
        scrollwheel: container.dataset.scrollZoom === 'true',
    });

    locations.forEach(loc => {
        const marker = new google.maps.Marker({
            position: { lat: loc.lat, lng: loc.lng },
            map,
            title: loc.title,
        });

        if (loc.title || loc.address) {
            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div class="p-2">
                        ${loc.title ? `<strong>${loc.title}</strong>` : ''}
                        ${loc.address ? `<p class="text-sm text-gray-600">${loc.address}</p>` : ''}
                    </div>
                `,
            });

            marker.addListener('click', () => {
                infoWindow.open(map, marker);
            });
        }
    });
}
```

---

## Part 5: Business Hours Block

### Hours Configuration

```typescript
{
    id: 'hours_1',
    type: 'business-hours',
    settings: {
        layout: 'list' | 'table' | 'compact',
        showTodayHighlight: true,
        showClosedDays: true,
        use24Hour: false,
    },
    content: {
        en: {
            title: 'Business Hours',
            closedLabel: 'Closed',
            todayLabel: 'Today',
            days: {
                monday: 'Monday',
                tuesday: 'Tuesday',
                wednesday: 'Wednesday',
                thursday: 'Thursday',
                friday: 'Friday',
                saturday: 'Saturday',
                sunday: 'Sunday',
            }
        }
    }
}
```

### Hours Data Structure

```typescript
// In ContactConfig
businessHours: {
    monday: { open: '09:00', close: '18:00', closed: false },
    tuesday: { open: '09:00', close: '18:00', closed: false },
    wednesday: { open: '09:00', close: '18:00', closed: false },
    thursday: { open: '09:00', close: '18:00', closed: false },
    friday: { open: '09:00', close: '17:00', closed: false },
    saturday: { open: '10:00', close: '14:00', closed: false },
    sunday: { open: '', close: '', closed: true },
}
```

### Hours Block Renderer

```typescript
PageBuilderModule.registerRenderer('business-hours', (block, config) => {
    const settings = block.settings;
    const content = block.content[PageBuilderModule.currentLanguage] || block.content['en'];
    const hours = config.contact?.businessHours || {};

    const today = new Date().toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
    const dayNames = content?.days || {};
    const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    const formatTime = (time: string): string => {
        if (!time) return '';
        if (settings.use24Hour) return time;

        const [h, m] = time.split(':').map(Number);
        const period = h >= 12 ? 'PM' : 'AM';
        const hour = h % 12 || 12;
        return `${hour}:${m.toString().padStart(2, '0')} ${period}`;
    };

    const hoursHtml = days.map(day => {
        const dayHours = hours[day];
        if (!dayHours && !settings.showClosedDays) return '';

        const isToday = day === today;
        const isClosed = dayHours?.closed || !dayHours;
        const dayName = dayNames[day] || day.charAt(0).toUpperCase() + day.slice(1);

        return `
            <div class="flex justify-between py-2 ${isToday && settings.showTodayHighlight ? 'bg-primary/10 px-3 rounded' : ''}">
                <span class="${isToday ? 'font-semibold' : ''}">${dayName}</span>
                <span class="${isClosed ? 'text-gray-400' : 'font-medium'}">
                    ${isClosed
                        ? (content?.closedLabel || 'Closed')
                        : `${formatTime(dayHours.open)} - ${formatTime(dayHours.close)}`
                    }
                </span>
            </div>
        `;
    }).filter(Boolean).join('');

    return `
        <section class="py-12">
            <div class="max-w-md mx-auto px-4">
                ${content?.title ? `<h3 class="text-xl font-bold mb-4 text-center">${content.title}</h3>` : ''}
                <div class="bg-white rounded-lg shadow-md p-6 divide-y divide-gray-100">
                    ${hoursHtml}
                </div>
            </div>
        </section>
    `;
});
```

---

## Part 6: Multi-Location Block

### Locations Grid Block

```typescript
{
    id: 'locations_1',
    type: 'locations-grid',
    settings: {
        layout: 'grid' | 'list' | 'map-with-list',
        columns: 2,
        showMap: true,
        showAddress: true,
        showPhone: true,
        showEmail: true,
        showHours: false,
    },
    content: {
        en: {
            title: 'Our Locations',
            subtitle: 'Find us near you',
        }
    }
}
```

### Locations Data

```typescript
// In ContactConfig
additionalLocations: [
    {
        id: 'loc_1',
        name: 'Downtown Office',
        address: '123 Main Street, City, Country',
        phone: '+1 234 567 890',
        email: 'downtown@example.com',
        coordinates: { lat: 44.4268, lng: 26.1025 },
        hours: 'Mon-Fri: 9AM-6PM',
    },
    {
        id: 'loc_2',
        name: 'North Branch',
        address: '456 North Avenue, City, Country',
        phone: '+1 234 567 891',
        email: 'north@example.com',
        coordinates: { lat: 44.4500, lng: 26.1200 },
        hours: 'Mon-Sat: 10AM-8PM',
    }
]
```

---

## Part 7: Template Integration

### Adding Contact to Footer

```typescript
renderFooter: (config: TixelloConfig): string => {
    const contact = config.contact;

    return `
        <footer class="...">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- ... other columns ... -->

                <!-- Contact Column -->
                <div>
                    <h4 class="font-semibold mb-4">Contact</h4>
                    <ul class="space-y-2 text-sm">
                        ${contact?.email ? `
                            <li>
                                <a href="mailto:${contact.email}" class="flex items-center gap-2 hover:text-primary">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    ${contact.email}
                                </a>
                            </li>
                        ` : ''}
                        ${contact?.phone ? `
                            <li>
                                <a href="tel:${contact.phone}" class="flex items-center gap-2 hover:text-primary">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                    ${contact.phone}
                                </a>
                            </li>
                        ` : ''}
                        ${contact?.address ? `
                            <li class="flex items-start gap-2">
                                <svg class="w-4 h-4 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span>${contact.address.formatted}</span>
                            </li>
                        ` : ''}
                    </ul>
                </div>
            </div>
        </footer>
    `;
}
```

---

## Part 8: API Endpoints

### Get Contact Information

```
GET /api/tenant-client/contact
Headers: X-Tenant-ID or hostname parameter
```

Response:
```json
{
    "success": true,
    "data": {
        "email": "contact@example.com",
        "phone": "+1 234 567 890",
        "address": {
            "street": "123 Main St",
            "city": "New York",
            "formatted": "123 Main St, New York, NY 10001"
        },
        "coordinates": {
            "lat": 40.7128,
            "lng": -74.0060
        },
        "businessHours": {...},
        "additionalLocations": [...]
    }
}
```

### Submit Contact Form

```
POST /api/tenant-client/contact/submit
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1 234 567 890",
    "subject": "General Inquiry",
    "message": "Hello, I have a question..."
}
```

---

## Part 9: Implementation Checklist

- [ ] Add ContactConfig to TixelloConfig
- [ ] Create contact-info block renderer
- [ ] Create contact-form block renderer
- [ ] Create map block renderer
- [ ] Create business-hours block renderer
- [ ] Create locations-grid block renderer
- [ ] Add contact info to footer template
- [ ] Create API endpoints for contact data
- [ ] Create API endpoint for form submission
- [ ] Add Google Maps / Leaflet integration
- [ ] Add form validation (client & server)
- [ ] Add success/error handling for forms
- [ ] Add multi-language support for all blocks

---

**Document Version:** 1.0
**Last Updated:** 2024-12-10
