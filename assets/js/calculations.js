// Financial calculations for ABBIS
class ABBISCalculations {
    // Calculate duration between start and finish times
    static calculateDuration(startTime, finishTime) {
        if (!startTime || !finishTime) return 0;
        
        const start = this.timeToMinutes(startTime);
        const finish = this.timeToMinutes(finishTime);
        
        let diffMinutes = finish - start;
        if (diffMinutes < 0) diffMinutes += 24 * 60; // Cross midnight
        
        return diffMinutes;
    }

    // Convert time string to minutes
    static timeToMinutes(timeStr) {
        if (!timeStr) return 0;
        const [hours, minutes] = timeStr.split(':').map(Number);
        return hours * 60 + minutes;
    }

    // Format minutes to hours and minutes (e.g., "5h 9m" or "0h 45m")
    static formatDuration(minutes) {
        if (!minutes || minutes === 0) return '0h 0m';
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        if (hours > 0) {
            return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
        }
        return `${mins}m`;
    }

    // Calculate total RPM
    static calculateTotalRPM(startRPM, finishRPM) {
        return Math.max(0, (finishRPM || 0) - (startRPM || 0));
    }

    // Calculate total depth
    static calculateTotalDepth(rodLength, rodsUsed) {
        return (rodLength || 0) * (rodsUsed || 0);
    }

    // Calculate construction depth
    // Formula: (screen pipes + plain pipes) * 3 meters per pipe
    static calculateConstructionDepth(screenPipes, plainPipes) {
        const screen = parseFloat(screenPipes) || 0;
        const plain = parseFloat(plainPipes) || 0;
        return (screen + plain) * 3; // 3m per pipe
    }

    // Calculate financial totals (CORRECTED based on business logic)
    static calculateFinancialTotals(data) {
        const jobType = data.job_type || data.jobType || '';
        const balanceBF = parseFloat(data.balance_bf || data.balanceBF || 0);
        const contractSum = parseFloat(data.contract_sum || data.contractSum || 0);
        const rigFeeCharged = parseFloat(data.rig_fee_charged || data.rigFeeCharged || 0);
        const rigFeeCollected = parseFloat(data.rig_fee_collected || data.rigFeeCollected || 0);
        const cashReceived = parseFloat(data.cash_received || data.cashReceived || 0);
        const materialsIncome = parseFloat(data.materials_income || data.materialsIncome || 0);
        const materialsCost = parseFloat(data.materials_cost || data.materialsCost || 0);
        const wagesTotal = parseFloat(data.total_wages || data.wagesTotal || 0);
        const expensesTotal = parseFloat(data.daily_expenses || data.expensesTotal || 0);
        const momoTransfer = parseFloat(data.momo_transfer || data.momoTransfer || 0);
        const cashGiven = parseFloat(data.cash_given || data.cashGiven || 0);
        const bankDeposit = parseFloat(data.bank_deposit || data.bankDeposit || 0);

        // INCOME (+) - Positives
        let totalIncome = 0;
        
        // Balance B/F - company money at hand from previous day(s)
        totalIncome += balanceBF;
        
        // Full Contract Sum (only for direct jobs)
        if (jobType === 'direct') {
            totalIncome += contractSum;
            // For direct jobs, rig fee charged is deducted from contract sum
            // So we subtract it from income (or add it to expenses)
            if (rigFeeCharged > 0) {
                totalIncome -= rigFeeCharged; // Deduct rig fee from contract sum
            }
        }
        
        // Rig Fee Collected (from client) - always income
        totalIncome += rigFeeCollected;
        
        // Cash Received (from company, NOT from client)
        totalIncome += cashReceived;
        
        // Material Sold - money gotten from selling company materials
        totalIncome += materialsIncome;
        
        // EXPENSES (-) - Negatives
        let totalExpenses = 0;
        
        // Materials Purchased
        // Rule: If contractor job AND materials provided by client → NOT in cost
        const jobType = data.job_type || 'direct';
        const materialsProvidedBy = data.materials_provided_by || 'client';
        if (!(jobType === 'subcontract' && materialsProvidedBy === 'client')) {
            totalExpenses += materialsCost;
        }
        
        // Wages - Salaries or wages paid to workers
        totalExpenses += wagesTotal;
        
        // Loans - monies borrowed by workers
        const loansAmount = parseFloat(data.loans_amount || data.loansAmount || 0);
        totalExpenses += loansAmount;
        
        // Daily Expenses - total monies spent on business operations
        totalExpenses += expensesTotal;

        // Money Banked (deposits/savings)
        const totalMoneyBanked = momoTransfer + cashGiven + bankDeposit;

        // Net Profit (income - expenses, excluding deposits)
        const netProfit = totalIncome - totalExpenses;

        // Day's Balance - money remaining at hand after expenses and deposits
        // Start with Balance B/F (already included in totalIncome)
        // Add new income received today (excluding B/F which is already at hand)
        const newIncomeToday = totalIncome - balanceBF; // Remove B/F from income for balance calc
        const cashAtStart = balanceBF;
        const cashBeforeBanking = cashAtStart + newIncomeToday - totalExpenses;
        const daysBalance = cashBeforeBanking - totalMoneyBanked;

        // Outstanding Rig Fee
        const outstandingRigFee = Math.max(0, rigFeeCharged - rigFeeCollected);

        // Loans Outstanding (same as loans amount for now)
        const loansOutstanding = loansAmount;
        
        return {
            totalIncome: this.roundToTwo(totalIncome),
            totalExpenses: this.roundToTwo(totalExpenses),
            totalWages: this.roundToTwo(wagesTotal),
            netProfit: this.roundToTwo(netProfit),
            totalMoneyBanked: this.roundToTwo(totalMoneyBanked),
            daysBalance: this.roundToTwo(daysBalance),
            outstandingRigFee: this.roundToTwo(outstandingRigFee),
            materialsIncome: this.roundToTwo(materialsIncome),
            loansOutstanding: this.roundToTwo(loansOutstanding),
            totalDebt: this.roundToTwo(outstandingRigFee + loansOutstanding)
        };
    }

    // Calculate worker pay
    static calculateWorkerPay(units, rate, benefits, loanReclaim) {
        const amount = (parseFloat(units) * parseFloat(rate)) + parseFloat(benefits) - parseFloat(loanReclaim);
        return this.roundToTwo(Math.max(0, amount));
    }

    // Calculate expense amount
    static calculateExpenseAmount(unitCost, quantity) {
        return this.roundToTwo(parseFloat(unitCost) * parseFloat(quantity));
    }

    // Calculate materials value
    static calculateMaterialsValue(quantity, unitCost) {
        return this.roundToTwo(parseFloat(quantity) * parseFloat(unitCost));
    }

    // Round to 2 decimal places
    static roundToTwo(num) {
        return Math.round((num + Number.EPSILON) * 100) / 100;
    }

    // Format currency - uses system currency from window.SYSTEM_CURRENCY or defaults to GHS
    static formatCurrency(amount) {
        const currency = window.SYSTEM_CURRENCY || 'GHS';
        return currency + ' ' + this.roundToTwo(amount).toLocaleString('en-GH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

// Make available globally
window.ABBISCalculations = ABBISCalculations;