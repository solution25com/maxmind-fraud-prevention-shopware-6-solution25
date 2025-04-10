import template from './sw-order-list.html.twig';

Shopware.Component.override('sw-order-list', {
    template,

    methods: {
        getStatusBadgeClass(technicalName) {
            switch (technicalName) {
                case 'fraud_pass':
                    return 'sw-color-badge--fraud-pass';
                case 'fraud_review':
                    return 'sw-color-badge--fraud-review';
                case 'fraud_fail':
                    return 'sw-color-badge--fraud-fail';
                case 'in_progress':
                    return 'sw-color-badge--in-progress';
                case 'completed':
                    return 'sw-color-badge--completed';
                case 'cancelled':
                    return 'sw-color-badge--cancelled';
                default:
                    return '';
            }
        },

        getVariantFromOrderState(item) {
            const technicalName = item.stateMachineState.technicalName;
            switch (technicalName) {
                case 'in_progress':
                    return 'primary';
                case 'completed':
                    return 'success';
                case 'cancelled':
                    return 'danger';
                case 'open':
                    return 'neutral';
                case 'fraud_pass':
                    return 'success';
                case 'fraud_review':
                    return 'primary';
                case 'fraud_fail':
                    return 'danger';
                default:
                    return 'neutral';
            }
        },
    },
});
