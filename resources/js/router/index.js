import { createRouter, createWebHistory } from 'vue-router'
import { route } from './router'
import { LOCAL_STORAGE_KEY, LOCAL_STORAGE_USER, ROLE } from "@/constants/index.js"

const router = createRouter({
  history: createWebHistory(),
  routes: route,
  scrollBehavior(to, from, savedPosition) {
    return { top: 0 }
  }
});

router.beforeEach((to, from, next) => {
  const loginToken = localStorage.getItem(LOCAL_STORAGE_KEY.LOGIN_TOKEN);
  const savedUser = localStorage.getItem(LOCAL_STORAGE_USER.USER);
  const user = savedUser ? JSON.parse(savedUser) : null;
  const userRole = user?.role;
  const isSuperAdmin = user?.is_super_admin === true;
  const hasSeenOnboarding = localStorage.getItem(LOCAL_STORAGE_KEY.ONBOARDING) === "true";

  const publicPages = [
    "login", "register", "verify-email", "verify",
    "forgot-password", "reset-password", "login-success", "privacy-policy", "onboarding", 'complete-registration', 'verify-change-password', 'reset-password', 'tournament-landing'
  ];

  if (!loginToken) {
    const onboardingWhitelist = ["onboarding", "privacy-policy", "tournament-landing"];

    if (!hasSeenOnboarding && !onboardingWhitelist.includes(to.name)) {
      return next({ name: "onboarding", query: { redirect: to.fullPath }});
    }
  
    if (!publicPages.includes(to.name)) {
      return next({ name: "login", query: { redirect: to.fullPath } });
    }
  }  

  if (loginToken && publicPages.includes(to.name) && to.name !== "privacy-policy" && to.name !== "tournament-landing") {
    switch (userRole) {
      case ROLE.ADMIN:
        return next({ name: "dashboard" });
      case ROLE.REFEREE:
        return next({ name: "referee.dashboard" });
      case ROLE.PLAYER:
        return next({ name: "dashboard" });
      default:
        return next({ name: "dashboard" });
    }
  }

  if (loginToken && to.meta?.requiresAdmin && !isSuperAdmin) {
    return next({ name: "forbidden" });
  }

  next();
});

export default router;