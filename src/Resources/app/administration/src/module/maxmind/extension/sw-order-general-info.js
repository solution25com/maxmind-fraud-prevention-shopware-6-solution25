import template from './sw-order-general-info.html.twig';

Shopware.Component.override('sw-order-general-info', {
    template,

    methods: {
        getBackgroundStyle() {
            const technicalName = this.order.stateMachineState.technicalName;
            switch (technicalName) {
                case 'in_progress':
                    return 'sw-order-state__progress-select';
                case 'completed':
                    return 'sw-order-state__completed-select';
                case 'cancelled':
                    return 'sw-order-state__cancelled-select';
                case 'open':
                    return 'sw-order-state__open-select';
                case 'fraud_pass':
                    return 'sw-order-state__completed-select';
                case 'fraud_review':
                    return 'sw-order-state__progress-select';
                case 'fraud_fail':
                    return 'sw-order-state__cancelled-select';
                default:
                    return 'sw-order-state__open-select';
            }
        },
    },
});
