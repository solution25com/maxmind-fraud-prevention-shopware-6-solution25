import { Component } from 'Shopware';

Component.override('sw-order-list', {
    computed: {
        orderColumns() {
            const columns = this.$super('orderColumns');

            columns.push({
                property: 'customFields.maxmind_fraud_risk',
                label: 'Fraud Risk Score',
                allowResize: true,
                align: 'right',
                sortable: true,
                rawData: true,
                renderer(value, order) {
                    const fraudScore = order?.customFields?.maxmind_fraud_risk;
                    return fraudScore !== null && fraudScore !== undefined
                        ? parseFloat(fraudScore)
                        : '-';
                },
            });

            return columns;
        },
    },
});
