<template>
  <div
      class="bg-white rounded-lg shadow overflow-hidden cursor-pointer hover:shadow-md transition-shadow"
      @click="$emit('click')"
  >
    <div class="p-5">
      <div class="flex justify-between items-start mb-3">
        <h3 class="text-lg font-medium text-gray-900">{{ training.title }}</h3>
        <span
            v-if="booked"
            class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded-full"
        >
          Записаны
        </span>
      </div>

      <div class="flex justify-between mb-3">
        <div class="flex items-center text-gray-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
          </svg>
          {{ formatDate(training.date) }}
        </div>
        <div class="text-gray-600">
          {{ training.time }}
        </div>
      </div>

      <div class="flex justify-between items-center">
        <div class="text-lg font-bold text-primary">
          {{ training.price }} руб.
        </div>

        <div class="text-sm text-gray-600">
          {{ getSlotsText(training.slots_available, training.slots) }}
        </div>
      </div>
    </div>

    <div class="bg-gray-50 px-5 py-3 flex justify-between items-center">
      <div class="text-sm">
        <span
            :class="getAvailabilityClass(training.slots_available)"
            class="inline-block rounded-full w-3 h-3 mr-1"
        ></span>
        {{ getAvailabilityText(training.slots_available) }}
      </div>

      <button
          class="text-primary hover:text-primary/80 text-sm font-medium"
      >
        Подробнее
      </button>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';

defineEmits(['click']);

const props = defineProps({
  training: {
    type: Object,
    required: true
  },
  booked: {
    type: Boolean,
    default: false
  }
});

const formatDate = (dateString) => {
  const options = { weekday: 'short', day: 'numeric', month: 'short' };
  return new Date(dateString).toLocaleDateString('ru-RU', options);
};

const getSlotsText = (available, total) => {
  return `${available} из ${total} мест`;
};

const getAvailabilityClass = (available) => {
  if (available <= 0) return 'bg-red-500';
  if (available <= 3) return 'bg-yellow-500';
  return 'bg-green-500';
};

const getAvailabilityText = (available) => {
  if (available <= 0) return 'Нет мест';
  if (available <= 3) return 'Мало мест';
  return 'Доступно';
};
</script>