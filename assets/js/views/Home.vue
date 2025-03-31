<template>
  <div>
    <h1 class="text-2xl font-bold mb-6">Доступные тренировки</h1>

    <div class="mb-6">
      <div class="flex flex-wrap items-center gap-4">
        <div class="w-full md:w-auto">
          <input
              type="date"
              v-model="filters.date"
              class="input w-full"
              :min="today"
          />
        </div>
        <button
            @click="resetFilters"
            class="btn btn-secondary"
        >
          Сбросить фильтры
        </button>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="trainings.length === 0" class="bg-white rounded-lg shadow p-6 text-center">
      <p class="text-lg text-gray-600">Нет доступных тренировок на выбранную дату</p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <TrainingCard
          v-for="training in trainings"
          :key="training.id"
          :training="training"
          @click="viewTraining(training.id)"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import TrainingCard from '../components/TrainingCard.vue';
import { useTrainingStore } from '../stores/training';

const router = useRouter();
const trainingStore = useTrainingStore();

const loading = ref(true);
const filters = ref({
  date: new Date().toISOString().split('T')[0] // Today's date in YYYY-MM-DD format
});

const today = computed(() => {
  return new Date().toISOString().split('T')[0];
});

const trainings = computed(() => {
  return trainingStore.availableTrainings;
});

const fetchTrainings = async () => {
  loading.value = true;
  try {
    await trainingStore.fetchAvailableTrainings(filters.value);
  } catch (error) {
    console.error('Error fetching trainings:', error);
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  filters.value = {
    date: new Date().toISOString().split('T')[0]
  };
};

const viewTraining = (id) => {
  router.push({ name: 'training-details', params: { id } });
};

watch(() => filters.value.date, () => {
  fetchTrainings();
});

onMounted(() => {
  fetchTrainings();
});
</script>