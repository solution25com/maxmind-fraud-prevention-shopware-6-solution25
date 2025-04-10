import template from './sw-order-detail-general.html.twig';

Shopware.Component.override('sw-order-detail-general', {
    template,

    data() {
        return {
            showWarningsFactors: false,
        };
    },

    computed: {
        fraudScore() {
            if (
                !this.order?.customFields ||
                this.order.customFields.maxmind_fraud_risk === undefined
            ) {
                return 'N/A';
            }
            return this.order.customFields.maxmind_fraud_risk;
        },

        overallRiskScore() {
            return (
                this.order?.customFields?.maxmind_overall_risk_score || 'N/A'
            );
        },

        ipRiskScore() {
            return this.order?.customFields?.maxmind_ip_risk_score || 'N/A';
        },

        transactionId() {
            return this.order?.customFields?.maxmind_transaction_id || '';
        },

        transactionUrl() {
            return this.order?.customFields?.maxmind_transaction_url || '#';
        },

        warningsFactors() {
            const factors =
                this.order?.customFields?.maxmind_warnings_factors || [];
            return Array.isArray(factors) ? factors : [];
        },

        hasMaxMindData() {
            return (
                this.fraudScore !== 'N/A' ||
                this.overallRiskScore !== 'N/A' ||
                this.ipRiskScore !== 'N/A' ||
                this.transactionId ||
                this.transactionUrl !== '#' ||
                this.warningsFactors.length > 0
            );
        },
    },

    methods: {
        copyTransactionId() {
            if (!this.transactionId) {
                this.createNotificationError({
                    message: this.$tc(
                        'sw-order-detail-general.maxmindFraudDetection.noTransactionId'
                    ),
                });
                return;
            }

            navigator.clipboard
                .writeText(this.transactionId)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc(
                            'sw-order-detail-general.maxmindFraudDetection.transactionIdCopied'
                        ),
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc(
                            'sw-order-detail-general.maxmindFraudDetection.copyFailed'
                        ),
                    });
                });
        },

        toggleWarningsFactors() {
            this.showWarningsFactors = !this.showWarningsFactors;
        },

        createNotificationSuccess({ message }) {
            this.$root.$emit('notification-create', {
                type: 'success',
                message,
            });
        },

        createNotificationError({ message }) {
            this.$root.$emit('notification-create', {
                type: 'error',
                message,
            });
        },
    },
});
