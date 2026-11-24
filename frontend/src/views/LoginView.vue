<script setup>
import {
  Leaf,
  UserRound,
  Lock,
  Eye,
  EyeOff,
  Info,
  ArrowRight,
  Users,
  FileText,
  ArrowLeft,
  Car,
} from 'lucide-vue-next'
import { motion } from 'motion-v'
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { roleHomeMap } from '../router'

const router = useRouter()
const route = useRoute()

const username = ref('')
const password = ref('')
const showPassword = ref(false)
const selectedRole = ref('')
const statusMessage = ref('')
const errorMessage = ref('')
const isSubmitting = ref(false)

const parkingHeroPhoto =
  '../src/assets/login-image.jpe'

const quickRoles = [
  { id: 'admin', label: 'Admin', sampleUser: 'admin', icon: UserRound },
  { id: 'petugas', label: 'Petugas', sampleUser: 'petugas', icon: Users },
  { id: 'owner', label: 'Owner', sampleUser: 'owner', icon: FileText },
]

const fillRole = (role) => {
  selectedRole.value = role.id
  username.value = role.sampleUser
  errorMessage.value = ''
  statusMessage.value = `Username "${role.sampleUser}" terisi. Masukkan kata sandi lalu klik Masuk ke Sistem.`
}

const handleSubmit = async () => {
  errorMessage.value = ''
  statusMessage.value = ''

  if (!username.value.trim() || !password.value) {
    errorMessage.value = 'Username dan kata sandi wajib diisi.'
    return
  }

  isSubmitting.value = true
  try {
    const { data } = await api.post('/auth/login', {
      username: username.value.trim(),
      password: password.value,
    })

    const token = data.access_token
    const user = data.user

    if (!token || !user) {
      errorMessage.value = 'Respons login tidak valid dari server.'
      return
    }

    localStorage.setItem('token', token)
    localStorage.setItem('user', JSON.stringify(user))

    statusMessage.value = data.message || 'Login berhasil, mengalihkan...'

    const targetPath =
      (typeof route.query.redirect === 'string' && route.query.redirect) ||
      roleHomeMap[user.role] ||
      '/admin/dashboard'

    await router.push(targetPath)
  } catch (err) {
    errorMessage.value =
      err.response?.data?.message ||
      'Gagal terhubung ke server. Pastikan backend berjalan dan CORS sudah diizinkan.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#FFFFFF] text-[#0F172A]">
    <div class="mx-auto flex min-h-screen max-w-[1280px] flex-col justify-between px-5 py-6 sm:px-8 lg:px-10">
      <div class="flex items-center justify-between">
        <button
          type="button"
          @click="router.push('/')"
          class="group inline-flex items-center gap-2 rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2 text-xs font-semibold text-[#475569] transition-colors hover:border-olive hover:text-olive"
        >
          <ArrowLeft :size="14" class="transition-transform duration-200 ease-out group-hover:-translate-x-0.5" />
          <span>Kembali ke Beranda</span>
        </button>

        <span class="hidden items-center gap-1.5 rounded-full bg-[#F8FAFC] px-3 py-1.5 text-[11px] font-medium text-[#64748B] sm:inline-flex">
          <Car :size="13" class="text-olive" />
          ParkFlow
        </span>
      </div>

      <div class="my-6 grid items-center gap-8 lg:grid-cols-[3fr_2fr] lg:gap-10">
        <motion.div
          :initial="{ opacity: 0, y: 12 }"
          :animate="{ opacity: 1, y: 0 }"
          :transition="{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }"
          class="mx-auto w-full max-w-[400px] will-change-transform"
        >
          <div class="flex flex-col items-center text-center">
            <div class="relative mb-3.5">
              <div class="flex h-14 w-14 items-center justify-center rounded-full bg-[#D5EC96] text-2xl font-extrabold text-olive shadow-2xs">
                <Car :size="32" />
              </div>
             
            </div>

            <h1 class="text-2xl font-extrabold tracking-tight text-[#0F172A] sm:text-[26px]">
              Log in ke ParkFlow
            </h1>
            <p class="mt-1 text-xs text-[#64748B]">
              Sistem Operasional Parkir Terpadu
            </p>
          </div>

          <form @submit.prevent="handleSubmit" class="mt-6 space-y-4">
            <div>
              <label for="username" class="mb-1.5 block text-xs font-semibold text-[#334155]">
                Username Akun
              </label>
              <div class="relative flex items-center">
                <UserRound :size="16" class="pointer-events-none absolute left-4 text-[#94A3B8]" />
                <input
                  id="username"
                  v-model="username"
                  type="text"
                  autocomplete="username"
                  placeholder="admin / petugas / owner"
                  class="w-full rounded-full border border-[#E2E8F0] bg-[#FFFFFF] py-3 pl-10 pr-4 text-xs text-[#0F172A] placeholder:text-[#94A3B8] transition-colors focus:border-olive focus:outline-none"
                />
              </div>
            </div>

            <div>
              <div class="mb-1.5 flex items-center justify-between">
                <label for="password" class="block text-xs font-semibold text-[#334155]">
                  Kata Sandi
                </label>
                <a
                  href="#lupa-sandi"
                  @click.prevent="statusMessage = 'Hubungi Admin Sistem untuk reset kata sandi.'"
                  class="text-[11px] font-semibold text-olive hover:underline"
                >
                  Lupa sandi?
                </a>
              </div>
              <div class="relative flex items-center">
                <Lock :size="16" class="pointer-events-none absolute left-4 text-[#94A3B8]" />
                <input
                  id="password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  placeholder="••••••••••••"
                  class="w-full rounded-full border border-[#E2E8F0] bg-[#FFFFFF] py-3 pl-10 pr-11 text-xs text-[#0F172A] placeholder:text-[#94A3B8] transition-colors focus:border-olive focus:outline-none"
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute right-3.5 rounded-full p-1 text-[#64748B] transition-colors hover:text-olive"
                  aria-label="Tampilkan atau sembunyikan kata sandi"
                >
                  <EyeOff v-if="showPassword" :size="16" />
                  <Eye v-else :size="16" />
                </button>
              </div>
            </div>

            <div class="flex items-center gap-3 rounded-2xl border border-[#E2E8F0]/80 bg-[#F4F8EA]/70 px-3.5 py-2.5 text-[11px] leading-4 text-[#475569]">
              <Info :size="15" class="shrink-0 text-olive" />
              <span>Sistem otomatis mengarahkan ke dashboard berdasarkan role akun</span>
            </div>

            <motion.button
              type="submit"
              :disabled="isSubmitting"
              :while-hover="{ y: -2, scale: 1.01 }"
              :while-press="{ scale: 0.98 }"
              :transition="{ type: 'spring', stiffness: 420, damping: 24 }"
              class="group mt-2 flex w-full items-center justify-center gap-2 rounded-full bg-olive px-6 py-3.5 text-xs font-bold text-white shadow-2xs transition-colors hover:bg-olive-dark disabled:cursor-not-allowed disabled:opacity-60 will-change-transform"
            >
              <span>{{ isSubmitting ? 'Memproses...' : 'Masuk ke Sistem' }}</span>
              <ArrowRight :size="15" class="transition-transform duration-200 ease-out group-hover:translate-x-1" />
            </motion.button>

            <p v-if="errorMessage" class="text-center text-[11px] font-semibold text-rose-600">
              {{ errorMessage }}
            </p>
            <p v-else-if="statusMessage" class="text-center text-[11px] font-medium text-olive">
              {{ statusMessage }}
            </p>
          </form>

          <div class="mt-6 pt-1">
            <p class="text-center text-[10px] font-bold tracking-[0.14em] text-[#94A3B8]">
              AKSES CEPAT SIMULATOR ROLE
            </p>

            <div class="mt-3 grid grid-cols-3 gap-2.5">
              <motion.button
                v-for="role in quickRoles"
                :key="role.id"
                type="button"
                @click="fillRole(role)"
                :while-hover="{ y: -2 }"
                :while-press="{ scale: 0.97 }"
                :transition="{ type: 'spring', stiffness: 420, damping: 24 }"
                :class="[
                  'flex items-center justify-center gap-1.5 rounded-full border px-3 py-2 text-xs font-semibold transition-colors will-change-transform',
                  selectedRole === role.id
                    ? 'border-olive bg-[#D5EC96]/55 text-olive'
                    : 'border-[#E2E8F0] bg-[#FFFFFF] text-[#334155] hover:border-olive/50 hover:text-olive'
                ]"
              >
                <component :is="role.icon" :size="14" class="shrink-0 text-olive" />
                <span>{{ role.label }}</span>
              </motion.button>
            </div>
          </div>
        </motion.div>

        <div class="hidden lg:block">
          <div class="relative aspect-[4/5] max-h-[560px] w-full overflow-hidden rounded-[24px] border border-[#E2E8F0] bg-[#F8FAFC]">
            <img
              :src="parkingHeroPhoto"
              alt="Area parkir teratur ParkFlow"
              class="block h-full w-full object-cover object-center"
            />
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#0F172A]/45 via-transparent to-transparent" />
            <div class="absolute bottom-4 left-4 right-4 flex items-center justify-between gap-3 rounded-2xl border border-white/30 bg-white/90 px-4 py-3">
              <div>
                <p class="text-xs font-bold text-[#0F172A]">Pemantauan Slot &amp; Karcis Barcode</p>
                <p class="text-[11px] text-[#64748B]">Akses terpusat untuk Admin, Petugas, dan Owner</p>
              </div>
              <span class="shrink-0 rounded-full bg-[#D5EC96] px-3 py-1 text-[10px] font-bold text-olive">
                Real-Time
              </span>
            </div>
          </div>
        </div>
      </div>

      <p class="text-center text-[10px] text-[#94A3B8] lg:text-left">
        © 2026 ParkFlow Smart Parking Management
      </p>
    </div>
  </div>
</template>

<style scoped>
.text-olive {
  color: #50631D;
}
.bg-olive {
  background-color: #50631D;
}
.hover\:bg-olive-dark:hover {
  background-color: #3B4A14;
}
</style>