<template>
  <div>
    <h1 class="text-2xl font-bold mb-6">Мои тренировки</h1>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="userTrainings.length === 0" class="bg-white rounded-lg shadow p-6 text-center">
      <p class="text-lg text-gray-600">У вас пока нет забронированных тренировок</p>
      <router-link to="/" class="btn btn-primary mt-4 inline-block">
        Выбрать тренировку
      </router-link>
    </div>

    <div v-else>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <TrainingCard
            v-for="training in userTrainings"
            :key="training.id"
            :training="training"
            :booked="true"
            @click="viewTraining(training.id)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import TrainingCard from '../components/TrainingCard.vue';
import { useTrainingStore } from '../stores/training';

const router = useRouter();
const trainingStore = useTrainingStore();
const loading = ref(true);

const userTrainings = computed(() => {
  return trainingStore.userTrainings;
});

const fetchUserTrainings = async () => {
  loading.value = true;
  try {
    await trainingStore.fetchUserTrainings();
  } catch (error) {
    console.error('Error fetching user trainings:', error);
  } finally {
    loading.value = false;
  }
};

const viewTraining = (id) => {
  router.push({ name: 'training-details', params: { id } });
};

onMounted(() => {
  fetchUserTrainings();
});
</script>