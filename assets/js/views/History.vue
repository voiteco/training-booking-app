<template>
  <div>
    <h1 class="text-2xl font-bold mb-6">История тренировок</h1>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="bookings.length === 0" class="bg-white rounded-lg shadow p-6 text-center">
      <p class="text-lg text-gray-600">У вас пока нет истории посещений</p>
      <router-link to="/" class="btn btn-primary mt-4 inline-block">
        Выбрать тренировку
      </router-link>
    </div>

    <div v-else>
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Дата
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Название
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Статус
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Действия
            </th>
          </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="booking in bookings" :key="booking.id">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">{{ formatDate(booking.training?.date) }}</div>
              <div class="text-sm text-gray-500">{{ booking.training?.time }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-gray-900">
                {{ booking.training?.title }}
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span :class="getStatusClass(booking.status)">
                  {{ getStatusText(booking.status) }}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <button
                  v-if="canCancel(booking)"
                  @click="cancelBooking(booking.id)"
                  class="text-red-600 hover:text-red-900"
              >
                Отменить
              </button>
              <button
                  v-else-if="isPastTraining(booking.training?.date)"
                  class="text-primary hover:text-primary/80"
                  @click="viewTraining(booking.training?.id)"
              >
                Подробнее
              </button>
            </td>
          </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBookingStore } from '../stores/booking';

const router = useRouter();
const bookingStore = useBookingStore();
const loading = ref(true);

const bookings = computed(() => {
  return bookingStore.bookingHistory;
});

const fetchBookingHistory = async () => {
  loading.value = true;
  try {
    await bookingStore.fetchBookingHistory();
  } catch (error) {
    console.error('Error fetching booking history:', error);
  } finally {
    loading.value = false;
  }
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const options = { year: 'numeric', month: 'long', day: 'numeric' };
  return new Date(dateString).toLocaleDateString('ru-RU', options);
};

const getStatusText = (status) => {
  const statusMap = {
    'active': 'Активна',
    'completed': 'Завершена',
    'cancelled': 'Отменена'
  };
  return statusMap[status] || status;
};

const getStatusClass = (status) => {
  const baseClasses = 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full';
  const statusClassMap = {
    'active': `${baseClasses} bg-green-100 text-green-800`,
    'completed': `${baseClasses} bg-blue-100 text-blue-800`,
    'cancelled': `${baseClasses} bg-gray-100 text-gray-800`
  };
  return statusClassMap[status] || `${baseClasses} bg-gray-100 text-gray-800`;
};

const canCancel = (booking) => {
  return booking.status === 'active' && !isPastTraining(booking.training?.date);
};

const isPastTraining = (dateString) => {
  if (!dateString) return false;
  const trainingDate = new Date(dateString);
  trainingDate.setHours(23, 59, 59);
  return trainingDate < new Date();
};

const cancelBooking = async (bookingId) => {
  if (!confirm('Вы уверены, что хотите отменить запись на эту тренировку?')) {
    return;
  }

  try {
    await bookingStore.cancelBooking(bookingId);
    await fetchBookingHistory();
  } catch (error) {
    console.error('Error cancelling booking:', error);
    alert('Произошла ошибка при отмене записи. Пожалуйста, попробуйте еще раз.');
  }
};

const viewTraining = (id) => {
  if (id) {
    router.push({ name: 'training-details', params: { id } });
  }
};

onMounted(() => {
  fetchBookingHistory();
});
</script>