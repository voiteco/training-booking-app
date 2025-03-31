import { createRouter, createWebHistory } from 'vue-router';

// Импорт компонентов представлений
const Home = () => import('../views/Home.vue');
const MyTrainings = () => import('../views/MyTrainings.vue');
const TrainingDetails = () => import('../views/TrainingDetails.vue');
const History = () => import('../views/History.vue');

const routes = [
    {
        path: '/',
        name: 'home',
        component: Home,
        meta: { title: 'Доступные тренировки' }
    },
    {
        path: '/my-trainings',
        name: 'my-trainings',
        component: MyTrainings,
        meta: { title: 'Мои тренировки' }
    },
    {
        path: '/trainings/:id',
        name: 'training-details',
        component: TrainingDetails,
        props: true,
        meta: { title: 'Детали тренировки' }
    },
    {
        path: '/history',
        name: 'history',
        component: History,
        meta: { title: 'История тренировок' }
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes
});

// Изменение заголовка страницы при навигации
router.beforeEach((to, from, next) => {
    document.title = to.meta.title ? `${to.meta.title} | Тренировки` : 'Система записи на тренировки';
    next();
});

export default router;