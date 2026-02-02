type EventHandler = (data?: any) => void;

export class EventBus {
    private events: Map<string, Set<EventHandler>> = new Map();

    on(event: string, handler: EventHandler): void {
        if (!this.events.has(event)) {
            this.events.set(event, new Set());
        }
        this.events.get(event)!.add(handler);
    }

    off(event: string, handler: EventHandler): void {
        const handlers = this.events.get(event);
        if (handlers) {
            handlers.delete(handler);
        }
    }

    emit(event: string, data?: any): void {
        const handlers = this.events.get(event);
        if (handlers) {
            handlers.forEach(handler => handler(data));
        }
    }

    once(event: string, handler: EventHandler): void {
        const wrappedHandler = (data?: any) => {
            handler(data);
            this.off(event, wrappedHandler);
        };
        this.on(event, wrappedHandler);
    }
}
