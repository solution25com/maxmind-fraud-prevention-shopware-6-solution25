import template from './sw-order-detail-general.html.twig';

Shopware.Component.override('sw-order-detail-general', {
    template,

    computed: {
        fraudScore() {
            if (!this.order?.customFields || this.order.customFields.maxmind_fraud_risk === undefined) {
                return 'N/A';
            }
            return this.order.customFields.maxmind_fraud_risk;
        }
    }
});
