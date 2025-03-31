<template>
  <div class="app min-h-screen">
    <header class="bg-white shadow">
      <div class="container mx-auto px-4 py-4 flex justify-between items-center">
        <router-link to="/" class="text-2xl font-bold text-primary">
          Тренировки
        </router-link>
        <nav>
          <ul class="flex space-x-6">
            <li>
              <router-link to="/" class="hover:text-primary transition-colors">
                Главная
              </router-link>
            </li>
            <li>
              <router-link to="/my-trainings" class="hover:text-primary transition-colors">
                Мои тренировки
              </router-link>
            </li>
            <li>
              <router-link to="/history" class="hover:text-primary transition-colors">
                История
              </router-link>
            </li>
          </ul>
        </nav>
      </div>
    </header>

    <main class="container mx-auto px-4 py-8">
      <router-view />
    </main>

    <footer class="bg-gray-100 py-6 mt-auto">
      <div class="container mx-auto px-4 text-center text-gray-600">
        &copy; {{ new Date().getFullYear() }} Система записи на тренировки
      </div>
    </footer>
  </div>
</template>

<script setup>
// Композиционный API
</script>