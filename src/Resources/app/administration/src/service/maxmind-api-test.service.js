const { ApiService } = Shopware.Classes;

class MaxMindApiTestService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'solu1-maxmind/test-connection') {
        super(httpClient, loginService, apiEndpoint);
    }

    check(payload = {}) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`_action/${this.getApiBasePath()}`, payload, { headers })
            .then((response) => ApiService.handleResponse(response));
    }
}

export default MaxMindApiTestService;
