import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';

export interface ValidationStep {
    step: string;
    sign_code: number | null;
    status: 'pending' | 'validated' | 'rejected';
    val_date: string | null;
    val_observation: string | null;
}

export interface ValidationStatus {
    cng_code: number;
    cng_status: boolean | null;
    is_rejected: boolean;
    is_fully_validated: boolean;
    current_step: string | null;
    steps: ValidationStep[];
}

export interface CurrentStep {
    current_step: string | null;
    sign_code: number | null;
    message: string;
}

@Injectable({ providedIn: 'root' })
export class ValidationCongeService {
    private baseUrl = environment.apiUrl + '/validation_conge';

    constructor(private http: HttpClient) { }

    /**
     * Get full validation status for a leave
     */
    getStatus(cngCode: number): Observable<ValidationStatus> {
        return this.http.get<ValidationStatus>(`${this.baseUrl}/status/${cngCode}`);
    }

    /**
     * Get current step info
     */
    getCurrentStep(cngCode: number): Observable<CurrentStep> {
        return this.http.get<CurrentStep>(`${this.baseUrl}/current/${cngCode}`);
    }

    /**
     * Approve a step (validate)
     */
    validate(cngCode: number, signCode: number, observation?: string): Observable<any> {
        return this.http.post(`${this.baseUrl}/approve`, {
            cng_code: cngCode,
            sign_code: signCode,
            observation: observation || ''
        });
    }

    /**
     * Reject a step
     */
    reject(cngCode: number, signCode: number, observation: string): Observable<any> {
        return this.http.post(`${this.baseUrl}/reject`, {
            cng_code: cngCode,
            sign_code: signCode,
            observation: observation
        });
    }

    /**
     * Get pending leaves for a signer
     */
    getPendingForSigner(signCode: number): Observable<any[]> {
        return this.http.get<any[]>(`${this.baseUrl}/pending/${signCode}`);
    }

    /**
     * Get applicable validation steps for an employee based on their position
     */
    getStepsForEmployee(empCode: number): Observable<{ emp_code: number, poste: string, steps: string[] }> {
        return this.http.get<{ emp_code: number, poste: string, steps: string[] }>(`${this.baseUrl}/steps/${empCode}`);
    }
}
