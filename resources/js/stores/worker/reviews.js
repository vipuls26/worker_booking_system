import { defineStore } from 'pinia';
import * as reviewsApi from '../../api/worker/reviews';

export const useWorkerReviewsStore = defineStore('workerReviews', {
    state: () => ({
        reviews: [],
        summary: { average: 0, count: 0 },
        meta: {},
        loading: false,
        filters: {
            search: '',
            rating: '',
            sort: 'latest',
            per_page: 10,
        },
    }),

    actions: {
        async fetch(page = 1) {
            this.loading = true;

            try {
                const response = await reviewsApi.listReviews({
                    search: this.filters.search || undefined,
                    rating: this.filters.rating || undefined,
                    sort: this.filters.sort,
                    per_page: this.filters.per_page,
                    page,
                });
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
