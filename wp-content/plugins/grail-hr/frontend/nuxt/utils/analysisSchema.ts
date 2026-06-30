// Zod schema used for immediate UX validation before WordPress performs authoritative backend validation.
import { z } from 'zod';

const confidence = z.enum(['low', 'medium', 'high']);

export const cvAnalysisSchema = z.object({
  candidate: z.object({
    full_name: z.string().max(190),
    email: z.string().max(190),
    phone: z.string().max(80),
    location: z.string().max(190),
    linkedin_url: z.string().max(255),
    portfolio_url: z.string().max(255),
  }),
  summary: z.object({
    profile_title: z.string().max(190),
    short_summary: z.string().max(350),
    long_summary: z.string().max(900),
  }),
  career_targeting: z.object({
    primary_job: z.object({
      label: z.string().max(190),
      normalized_label: z.string().max(190),
      confidence,
      evidence: z.string().max(180),
    }),
    associated_jobs: z.array(z.record(z.string(), z.unknown())),
  }),
  seniority: z.object({
    level: z.enum(['junior', 'confirmed', 'senior', 'lead', 'manager', 'expert', 'unknown']),
    years_experience_estimate: z.number().nullable(),
    confidence,
    evidence: z.string().max(220),
  }),
  experiences: z.array(z.record(z.string(), z.unknown())),
  education: z.array(z.record(z.string(), z.unknown())),
  skills: z.array(z.record(z.string(), z.unknown())),
  tools: z.array(z.record(z.string(), z.unknown())),
  know_how: z.array(z.record(z.string(), z.unknown())),
  soft_skills: z.array(z.record(z.string(), z.unknown())),
  languages: z.array(z.record(z.string(), z.unknown())),
  interests: z.array(z.record(z.string(), z.unknown())),
  warnings: z.array(z.record(z.string(), z.unknown())),
});
