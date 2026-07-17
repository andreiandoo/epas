// Backend `status` values (see EventsController::getEventStatus):
//   'published' | 'draft' | 'pending_review' | 'rejected' | 'cancelled' | 'postponed'
export const UNPUBLISHED_STATUSES = ['draft', 'pending_review', 'rejected'];

export function isUnpublished(event) {
  return event && UNPUBLISHED_STATUSES.includes(event.status);
}

export function categorizeEvent(event) {
  const now = new Date();
  const eventDate = new Date(event.event_date || event.starts_at);
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const eventDay = new Date(eventDate.getFullYear(), eventDate.getMonth(), eventDate.getDate());

  if (event.status === 'ended' || event.status === 'cancelled') {
    return 'past';
  }

  // Drafts / pending / rejected → own bucket so they surface at the top of
  // the selector with a "Nepublicat" chip. Ordered by date within the bucket
  // by the modal itself. Cancelled stays in 'past' (organizers can't operate
  // on it anyway).
  if (isUnpublished(event)) {
    return 'unpublished';
  }

  const diffMs = eventDay.getTime() - today.getTime();
  const diffDays = diffMs / (1000 * 60 * 60 * 24);

  // If the event is today and happening now (within a reasonable window)
  if (diffDays === 0) {
    const hoursDiff = (eventDate.getTime() - now.getTime()) / (1000 * 60 * 60);
    if (hoursDiff <= 0 && hoursDiff > -12) {
      return 'live';
    }
    return 'today';
  }

  if (diffDays < 0) {
    return 'past';
  }

  return 'future';
}

export function groupEventsByCategory(events) {
  const groups = {
    live: [],
    today: [],
    unpublished: [],
    future: [],
    past: [],
  };

  events.forEach(event => {
    const category = event.timeCategory || categorizeEvent(event);
    if (groups[category]) {
      groups[category].push({ ...event, timeCategory: category });
    }
  });

  return groups;
}

export function getCategoryLabel(category) {
  switch (category) {
    case 'live': return 'LIVE ACUM';
    case 'today': return 'AZI';
    case 'past': return 'EVENIMENTE TRECUTE';
    case 'future': return 'VIITOARE';
    case 'unpublished': return 'NEPUBLICATE';
    default: return category.toUpperCase();
  }
}
