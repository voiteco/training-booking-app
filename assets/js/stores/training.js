// assets/js/stores/training.js
import { defineStore } from 'pinia';
import axios from 'axios';

export const useTrainingStore = defineStore('training', {
    state: () => ({
        trainings: [],
        availableTrainings: [],
        userTrainings: [],
        currentTraining: null,
        loading: false,
        error: null
    }),

    actions: {
        async fetchTrainings(filters = {}) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.get('/api/trainings', { params: filters });
                this.trainings = response.data;
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.error || 'Failed to fetch trainings';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchAvailableTrainings(filters = {}) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.get('/api/trainings/available', { params: filters });
                this.availableTrainings = response.data;
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.error || 'Failed to fetch available trainings';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchUserTrainings() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.get('/api/trainings/user');
                this.userTrainings = response.data;
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.error || 'Failed to fetch user trainings';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchTrainingById(id) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.get(`/api/trainings/${id}`);
                this.currentTraining = response.data;
                return response.data;
            } catch (error) {
                this.error = error.response?.data?.error || 'Failed to fetch training details';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async bookTraining(trainingId) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.post(`/api/bookings`, { training_id: trainingId });

                // Update training in lists if it exists there
                this.updateTrainingBookingStatus(trainingId, true, response.data.id);

                return response.data;
            } catch (error) {
                this.error = error.response?.data?.error || 'Failed to book training';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchTrainingDetails(id) {
            return this.fetchTrainingById(id);
        },

        updateTrainingBookingStatus(trainingId, isBooked, bookingId = null) {
            const updateInList = (list) => {
                return list.map(training => {
                    if (training.id === parseInt(trainingId)) {
                        return {
                            ...training,
                            userBooked: isBooked,
                            userBookingId: isBooked ? bookingId : null,
                            bookedSlots: isBooked ? training.bookedSlots + 1 : training.bookedSlots - 1
                        };
                    }
                    return training;
                });
            };

            this.trainings = updateInList(this.trainings);
            this.availableTrainings = updateInList(this.availableTrainings);

            if (this.currentTraining && this.currentTraining.id === parseInt(trainingId)) {
                this.currentTraining = {
                    ...this.currentTraining,
                    userBooked: isBooked,
                    userBookingId: isBooked ? bookingId : null,
                    bookedSlots: isBooked ? this.currentTraining.bookedSlots + 1 : this.currentTraining.bookedSlots - 1
                };
            }
        }
    },

    getters: {
        getTrainingById: (state) => (id) => {
            return state.trainings.find(training => training.id === parseInt(id)) ||
                state.availableTrainings.find(training => training.id === parseInt(id)) ||
                state.userTrainings.find(training => training.id === parseInt(id));
        },

        upcomingTrainings: (state) => {
            const now = new Date();
            return state.trainings.filter(training => {
                const trainingDate = new Date(training.date);
                return trainingDate >= now;
            });
        }
    },
});