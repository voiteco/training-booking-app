<template>
  <div>
    <div class="mb-6">
      <router-link to="/" class="text-primary hover:underline inline-flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
        </svg>
        Назад к списку
      </router-link>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="!training" class="bg-white rounded-lg shadow p-6 text-center">
      <p class="text-lg text-gray-600">Тренировка не найдена или была удалена</p>
      <router-link to="/" class="btn btn-primary mt-4 inline-block">
        Вернуться к списку тренировок
      </router-link>
    </div>

    <div v-else>
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6">
          <h1 class="text-2xl font-bold mb-2">{{ training.title }}</h1>

          <div class="flex flex-wrap gap-4 mb-4 text-gray-600">
            <div class="flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
              </svg>
              {{ formatDate(training.date) }}
            </div>
            <div class="flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
              </svg>
              {{ training.time }}
            </div>
            <div class="flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2h2a1 1 0 100-2H9z" clip-rule="evenodd" />
              </svg>
              {{ training.slots_available }} из {{ training.slots }} мест свободно
            </div>
            <div class="flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.736 6.979C9.208 6.193 9.696 6 10 6c.304 0 .792.193 1.264.979a1 1 0 001.715-1.029C12.279 4.784 11.232 4 10 4s-2.279.784-2.979 1.95a1 1 0 001.715 1.029zM7 12a1 1 0 110-2h6a1 1 0 110 2H7z" clip-rule="evenodd" />
              </svg>
              {{ training.price }} руб.
            </div>
          </div>

          <div class="mt-6">
            <div v-if="userBooking" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-green-800">
                    Вы уже записаны на эту тренировку
                  </p>
                  <button
                      @click="cancelBooking"
                      class="text-sm text-red-600 hover:text-red-800 font-medium mt-1 focus:outline-none"
                  >
                    Отменить запись
                  </button>
                </div>
              </div>
            </div>

            <div v-else-if="training.slots_available <= 0" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
              <p class="text-red-700">Все места на эту тренировку заняты</p>
            </div>

            <form v-else @submit.prevent="submitBooking" class="border border-gray-200 rounded-lg p-6">
              <h2 class="text-lg font-medium mb-4">Записаться на тренировку</h2>

              <div class="space-y-4">
                <div>
                  <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Ваше имя</label>
                  <input
                      type="text"
                      id="full_name"
                      v-model="form.full_name"
                      class="input w-full"
                      required
                  />
                </div>

                <div>
                  <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                  <input
                      type="email"
                      id="email"
                      v-model="form.email"
                      class="input w-full"
                      required
                  />
                </div>

                <div>
                  <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Телефон</label>
                  <input
                      type="tel"
                      id="phone"
                      v-model="form.phone"
                      class="input w-full"
                      required
                  />
                </div>

                <div class="flex items-start">
                  <div class="flex items-center h-5">
                    <input
                        id="confirmation"
                        type="checkbox"
                        v-model="form.confirmation"
                        class="focus:ring-primary h-4 w-4 text-primary border-gray-300 rounded"
                        required
                    />
                  </div>
                  <div class="ml-3 text-sm">
                    <label for="confirmation" class="font-medium text-gray-700">
                      Я согласен с правилами и условиями
                    </label>
                  </div>
                </div>
              </div>

              <div class="mt-6">
                <button
                    type="submit"
                    class="btn btn-primary w-full"
                    :disabled="submitting"
                >
                  <span v-if="submitting">Обработка...</span>
                  <span v-else>Записаться</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useTrainingStore } from '../stores/training';
import { useBookingStore } from '../stores/booking';
import { useUserStore } from '../stores/user';

const route = useRoute();
const router = useRouter();
const trainingStore = useTrainingStore();
const bookingStore = useBookingStore();
const userStore = useUserStore();

const loading = ref(true);
const submitting = ref(false);
const trainingId = computed(() => route.params.id);

const form = ref({
  full_name: '',
  email: '',
  phone: '',
  confirmation: false
});

const training = computed(() => {
  return trainingStore.getTrainingById(trainingId.value);
});

const userBooking = computed(() => {
  return bookingStore.getBookingForTraining(trainingId.value);
});

const fetchTrainingDetails = async () => {
  loading.value = true;
  try {
    await trainingStore.fetchTrainingById(trainingId.value);
    await bookingStore.fetchUserBookings();
  } catch (error) {
    console.error('Error fetching training details:', error);
  } finally {
    loading.value = false;
  }
};

const loadUserData = async () => {
  try {
    const userData = await userStore.getUserData();
    if (userData) {
      form.value.full_name = userData.full_name || '';
      form.value.email = userData.email || '';
      form.value.phone = userData.phone || '';
    }
  } catch (error) {
    console.error('Error loading user data:', error);
  }
};

const submitBooking = async () => {
  submitting.value = true;
  try {
    await bookingStore.createBooking({
      ...form.value,
      training_id: trainingId.value
    });

    // Save user data for future auto-fill
    await userStore.saveUserData({
      full_name: form.value.full_name,
      email: form.value.email,
      phone: form.value.phone
    });

    // Refresh training details
    await fetchTrainingDetails();

    // Clear form
    form.value.confirmation = false;
  } catch (error) {
    console.error('Error submitting booking:', error);
    alert('Произошла ошибка при записи на тренировку. Пожалуйста, попробуйте еще раз.');
  } finally {
    submitting.value = false;
  }
};

const cancelBooking = async () => {
  if (!confirm('Вы уверены, что хотите отменить запись на эту тренировку?')) {
    return;
  }

  try {
    await bookingStore.cancelBooking(userBooking.value.id);
    await fetchTrainingDetails();
  } catch (error) {
    console.error('Error cancelling booking:', error);
    alert('Произошла ошибка при отмене записи. Пожалуйста, попробуйте еще раз.');
  }
};

const formatDate = (dateString) => {
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  return new Date(dateString).toLocaleDateString('ru-RU', options);
};

onMounted(async () => {
  await fetchTrainingDetails();
  await loadUserData();
});
</script>