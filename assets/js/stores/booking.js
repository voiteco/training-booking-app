// assets/js/stores/booking.js
import { defineStore } from 'pinia';
import axios from 'axios';

export const useBookingStore = defineStore('booking', {
    state: () => ({
        bookingHistory: [],
        currentBooking: null,
        loading: false,
        error: null
    }),

    actions: {
        async fetchBookingHistory() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.get('/bookings/history');
                this.bookingHistory = response.data;
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.error || 'Failed to fetch booking history';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createBooking(bookingData) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.post('/bookings', bookingData);
                this.currentBooking = response.data;
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.error || 'Failed to create booking';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async cancelBooking(bookingId) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.delete(`/bookings/${bookingId}`);

                // Update bookingHistory after cancellation
                this.bookingHistory = this.bookingHistory.map(booking => {
                    if (booking.id === bookingId) {
                        return { ...booking, status: 'cancelled' };
                    }
                    return booking;
                });

                return response.data;
            } catch (error) {
                this.error = error.response?.data?.error || 'Failed to cancel booking';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchUserBookings() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.get('/api/bookings/user');
                this.bookingHistory = response.data;
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.error || 'Failed to fetch user bookings';
                throw error;
            } finally {
                this.loading = false;
            }
        }
    },

    getters: {
        activeBookings: (state) => {
            return state.bookingHistory.filter(booking => booking.status === 'active');
        },

        hasActiveBookings: (state) => {
            return state.bookingHistory.some(booking => booking.status === 'active');
        },

        getBookingById: (state) => (id) => {
            return state.bookingHistory.find(booking => booking.id === id);
        },

        getBookingForTraining: (state) => (trainingId) => {
            return state.bookingHistory.find(booking =>
                booking.training_id === parseInt(trainingId) &&
                booking.status !== 'cancelled'
            );
        }
    }
});