import { defineStore } from 'pinia';
import * as reviewsApi from '../../api/worker/reviews';

export const useWorkerReviewsStore = defineStore('workerReviews', {
    state: () => ({
        reviews: [],
        summary: { average: 0, count: 0 },
        meta: {},
        loading: false,
        filters: {
            rating: '',
            sort: 'latest',
            per_page: 10,
        },
    }),

    actions: {
        async fetch(page = 1) {
            this.loading = true;

            try {
                const response = await reviewsApi.listReviews({ ...this.filters, page });
                this.reviews = response.data.data.reviews;
                this.summary = response.data.data.summary;
                this.meta = response.data.data.meta;

                return response.data;
            } finally {
                this.loading = false;
            }
        },
    },
});
