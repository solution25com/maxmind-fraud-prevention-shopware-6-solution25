import { Component } from 'Shopware';

Component.override('sw-order-detail-general', {
    computed: {
        fraudRiskScore() {
            return this.order?.customFields?.maxmind_fraud_risk ?? '-';
        },
    },
});
