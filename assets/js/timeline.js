// Timeline: expand/collapse details on click, live updates via Mercure

// Open first timeline event by default
document.addEventListener('DOMContentLoaded', () => {
    const firstEvent = document.querySelector('.timeline-item .collapse');
    if (firstEvent) {
        firstEvent.classList.add('show');
    }
});

// Mercure live update: prepend new timeline events
document.addEventListener('mercure:update', (e) => {
    const timeline = document.querySelector('.timeline');
    if (!timeline) return;

    const { event, id, status, timestamp } = e.detail;
    const label = event.replace('GiftCard', '');

    const eventColors = {
        'GiftCardCreated': '#3b82f6', 'GiftCardActivated': '#10b981',
        'GiftCardRedeemed': '#06b6d4', 'GiftCardDepleted': '#ef4444',
        'GiftCardSuspended': '#f97316', 'GiftCardReactivated': '#10b981',
        'GiftCardCancelled': '#ef4444', 'GiftCardExpired': '#eab308',
        'GiftCardBalanceAdjusted': '#8b5cf6', 'GiftCardBalanceDecreased': '#ef4444'
    };
    const eventIcons = {
        'GiftCardCreated': 'bi-plus-circle-fill', 'GiftCardActivated': 'bi-play-circle-fill',
        'GiftCardRedeemed': 'bi-cart-dash-fill', 'GiftCardDepleted': 'bi-exclamation-circle-fill',
        'GiftCardSuspended': 'bi-pause-circle-fill', 'GiftCardReactivated': 'bi-arrow-counterclockwise',
        'GiftCardCancelled': 'bi-x-circle-fill', 'GiftCardExpired': 'bi-hourglass-bottom',
        'GiftCardBalanceAdjusted': 'bi-plus-slash-minus', 'GiftCardBalanceDecreased': 'bi-dash-circle-fill'
    };

    const color = eventColors[event] || '#6b7280';
    const icon = eventIcons[event] || 'bi-circle';
    const eventNum = 'live-' + Date.now();
    const time = timestamp ? timestamp.replace('T', ' ').substring(0, 19) : new Date().toISOString().replace('T', ' ').substring(0, 19);

    const item = document.createElement('div');
    item.className = 'timeline-item';
    item.style.animation = 'slideInRight 0.3s ease';
    item.innerHTML = `
        <div class="timeline-node" style="background: ${color};">
            <i class="bi ${icon}"></i>
        </div>
        <div class="timeline-content">
            <div class="timeline-header">
                <span class="timeline-event-name">${label}</span>
                <span class="timeline-time">${time}</span>
            </div>
            <div class="timeline-details" style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);">
                <div class="d-flex gap-3 flex-wrap">
                    <span class="badge" style="font-size:.7rem;">${(status || '').toUpperCase()}</span>
                </div>
            </div>
        </div>
    `;

    timeline.prepend(item);

    // Update event count badge
    const countBadge = document.querySelector('#event-timeline-card .card-header .badge');
    if (countBadge) {
        const current = parseInt(countBadge.textContent) || 0;
        const text = countBadge.textContent;
        countBadge.textContent = text.replace(/\d+/, current + 1);
    }
});
