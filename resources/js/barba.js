
import { initEventbriteWidgets } from './eventbrite';

initEventbriteWidgets();

barba.hooks.after(() => {
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
});

barba.init({
    transitions: [
        {
            name: "opacity-transition",
            leave(data) {
                return gsap.to(data.current.container, {
                    opacity: 0,
                });
            },
            enter(data) {
                return gsap.from(data.next.container, {
                    opacity: 0,
                });
            },
            afterEnter() {
                initEventbriteWidgets(); // 🔁 reinit here
            }
        },
    ],
});
