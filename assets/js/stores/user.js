// assets/js/stores/user.js
import { defineStore } from 'pinia';
import axios from 'axios';

export const useUserStore = defineStore('user', {
    state: () => ({
        userData: null,
        loading: false,
        error: null
    }),

    actions: {
        async getUserData() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.get('/api/user-data');

                if (response.data.data) {
                    this.userData = response.data.data;
                }

                return this.userData;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to fetch user data';
                return null;
            } finally {
                this.loading = false;
            }
        },

        async saveUserData(userData) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.post('/api/user-data', userData);

                if (response.data.success) {
                    this.userData = {
                        full_name: userData.full_name,
                        email: userData.email,
                        phone: userData.phone
                    };
                }

                return response.data;
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to save user data';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        clearUserData() {
            this.userData = null;
        }
    },

    getters: {
        isProfileComplete: (state) => {
            return state.userData &&
                state.userData.full_name &&
                state.userData.email &&
                state.userData.phone;
        },

        fullName: (state) => {
            return state.userData?.full_name || '';
        }
    }
});