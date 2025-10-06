<template>
  <!-- Dashboard Statistics -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6">
    <div :class="themeClasses.card" class="rounded-lg p-6">
      <div class="flex justify-between items-center mb-2">
        <h3 :class="[
          'text-lg font-semibold',
          themeClasses.text
        ]">Total Notes</h3>
        <font-awesome-icon :icon="['fas', 'book']" class="text-blue-500 text-xl" />
      </div>
      <div v-if="loading" class="animate-pulse">
        <div :class="[
          'h-8 rounded mb-2',
          themeClasses.card
        ]"></div>
        <div :class="[
          'h-4 rounded w-3/4',
          themeClasses.card
        ]"></div>
      </div>
      <div v-else>
        <p :class="[
          'text-3xl font-bold',
          themeClasses.text
        ]">{{ stats.totalNotes }}</p>
        <p :class="[
          'text-sm font-medium',
          themeClasses.secondaryText
        ]">{{ stats.notesThisWeek }} new this week</p>
      </div>
    </div>

    <div :class="themeClasses.card" class="rounded-lg p-6">
      <div class="flex justify-between items-center mb-2">
        <h3 :class="[
          'text-lg font-semibold',
          themeClasses.text
        ]">Study Time</h3>
        <font-awesome-icon :icon="['fas', 'clock']" class="text-green-500 text-xl" />
      </div>
      <div v-if="loading" class="animate-pulse">
        <div :class="[
          'h-8 rounded mb-2',
          themeClasses.card
        ]"></div>
        <div :class="[
          'h-4 rounded w-3/4',
          themeClasses.card
        ]"></div>
      </div>
      <div v-else>
        <p :class="[
          'text-3xl font-bold',
          themeClasses.text
        ]">{{ stats.studyHours }}h</p>
        <p :class="themeClasses.secondaryText" class="text-sm font-medium">{{ stats.studyHoursThisWeek }}h this week</p>
        <p :class="themeClasses.secondaryText" class="text-sm font-medium mt-1">{{ stats.totalNotesStudied }} notes studied</p>
      </div>
    </div>

    <div :class="themeClasses.card" class="rounded-lg p-6">
      <div class="flex justify-between items-center mb-2">
        <h3 :class="[
          'text-lg font-semibold',
          themeClasses.text
        ]">Goals</h3>
        <font-awesome-icon :icon="['fas', 'bullseye']" class="text-purple-500 text-xl" />
      </div>
      <div v-if="loading" class="animate-pulse">
        <div :class="[
          'h-8 rounded mb-2',
          themeClasses.card
        ]"></div>
        <div :class="[
          'h-4 rounded w-3/4',
          themeClasses.card
        ]"></div>
      </div>
      <div v-else>
        <p :class="[
          'text-3xl font-bold',
          themeClasses.text
        ]">{{ stats.activeGoals }}</p>
        <p :class="themeClasses.secondaryText" class="text-sm font-medium">{{ stats.completedGoals }} completed this month</p>
      </div>
    </div>

    <div :class="themeClasses.card" class="rounded-lg p-6">
      <div class="flex justify-between items-center mb-2">
        <h3 :class="[
          'text-lg font-semibold',
          themeClasses.text
        ]">Study Streak</h3>
        <font-awesome-icon :icon="['fas', 'fire']" class="text-orange-500 text-xl" />
      </div>
      <div v-if="loading" class="animate-pulse">
        <div :class="[
          'h-8 rounded mb-2',
          themeClasses.card
        ]"></div>
        <div :class="[
          'h-4 rounded w-3/4',
          themeClasses.card
        ]"></div>
      </div>
      <div v-else>
        <p :class="[
          'text-3xl font-bold',
          themeClasses.text
        ]">{{ stats.studyStreak }}</p>
        <p :class="themeClasses.secondaryText" class="text-sm font-medium">days in a row</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'StatisticsCard',
  props: {
    stats: {
      type: Object,
      required: true
    },
    loading: {
      type: Boolean,
      default: false
    },
    themeClasses: {
      type: Object,
      required: true
    }
  },
  mounted() {
    console.log('🔍 DEBUG: Statistics component mounted');
    console.log('🔍 DEBUG: Statistics props.stats:', this.stats);
    console.log('🔍 DEBUG: Statistics goals - activeGoals:', this.stats.activeGoals, 'completedGoals:', this.stats.completedGoals);
  },
  watch: {
    stats: {
      handler(newStats) {
        console.log('🔍 DEBUG: Statistics stats changed:', newStats);
        console.log('🔍 DEBUG: Statistics goals updated - activeGoals:', newStats.activeGoals, 'completedGoals:', newStats.completedGoals);
      },
      deep: true
    }
  }
}
</script>