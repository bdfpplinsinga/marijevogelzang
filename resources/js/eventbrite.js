
// eventbrite.js
export function initEventbriteWidgets() {
    console.log('initEventbriteWidgets');

    const modalTriggers = document.querySelectorAll('[id^="eventbrite-widget-modal-trigger-"]');

    if (!modalTriggers.length) return;

    modalTriggers.forEach(trigger => {
        const id = trigger.id.replace('eventbrite-widget-modal-trigger-', '');

        window.EBWidgets.createWidget({
            widgetType: 'checkout',
            eventId: id,
            modal: true,
            modalTriggerElementId: trigger.id,
            onOrderComplete: function () {
                console.log(`Order complete for event ${id}`);
            }
        });
    });
}